<?php

namespace App\Core\PDF;

class SimplePDF
{
    private array $pages = [[]];
    private int $currentPage = 0;

    private array $objects = [];
    private array $offsets = [];

    private int $pageWidth = 612;
    private int $pageHeight = 792;


    private float $bottomMargin = 60;



    public function text(
        float $x,
        float $y,
        string $text,
        int $size = 12,
        bool $bold = false
    ): void {

        $font = $bold ? "/F2" : "/F1";

        $text = $this->escape($text);

        $this->pages[$this->currentPage][] =
        "BT
        $font {$size} Tf
        {$x} {$y} Td
        ($text) Tj
        ET";
    }



    public function title(string $text): void
    {
        $this->text(
            50,
            740,
            $text,
            24,
            true
        );
    }



    public function heading(
        string $text,
        float $y
    ): void {

        $this->line(
            50,
            $y - 8,
            560,
            $y - 8
        );


        $this->pages[$this->currentPage][] =
            "BT
            /F2 14 Tf
            50 {$y} Td
            (" . $this->escape($text) . ") Tj
            ET";
    }



    public function line(
        float $x1,
        float $y1,
        float $x2,
        float $y2
    ): void {

        $this->pages[$this->currentPage][] =
        "
        $x1 $y1 m
        $x2 $y2 l
        S
        ";
    }




    public function box(
        float $x,
        float $y,
        float $w,
        float $h
    ): void {

        $this->pages[$this->currentPage][] =
        "
        $x $y m
        " .
        ($x+$w)." $y l
        " .
        ($x+$w)." ".($y+$h)." l
        $x ".($y+$h)." l
        h
        S
        ";
    }




    public function multiline(
        float $x,
        float $startY,
        array $lines,
        float $spacing = 18
    ): void {

        $y = $startY;


        foreach ($lines as $line) {

            $this->text(
                $x,
                $y,
                $line
            );

            $y -= $spacing;
        }
    }



    public function newPage(): void
    {
        $this->currentPage++;

        $this->pages[$this->currentPage] = [];
    }



    public function checkPageBreak(float &$y): void
    {
        if ($y < $this->bottomMargin) {

            $this->newPage();

            $y = 720;
        }
    }






    public function output(
        string $filename="document.pdf"
    ): void {


        $this->objects = [];

        $pageObjects = [];
        $contentObjects = [];



        // Catalog
        $this->objects[] =
        "<<
        /Type /Catalog
        /Pages 2 0 R
        >>";



        // Placeholder Pages object
        $this->objects[] = "";



        foreach ($this->pages as $pageIndex => $pageLines) {


            $content =
                implode(
                    "\n",
                    $pageLines
                );


            $contentObjectIndex =
                count($this->objects) + 1;


            $this->objects[] =
            "<<
            /Length ".strlen($content)."
            >>
            stream
            $content
            endstream";


            $contentObjects[] =
                $contentObjectIndex;



            $pageObjectIndex =
                count($this->objects) + 1;



            $this->objects[] =
            "<<
            /Type /Page
            /Parent 2 0 R
            /MediaBox [0 0 {$this->pageWidth} {$this->pageHeight}]
            /Contents {$contentObjectIndex} 0 R

            /Resources
            <<
            /Font
            <<
            /F1 ".($pageObjectIndex + 1)." 0 R
            /F2 ".($pageObjectIndex + 2)." 0 R
            >>
            >>
            >>";


            $pageObjects[] =
                $pageObjectIndex;
        }





        // Pages object
        $this->objects[1] =
        "<<
        /Type /Pages
        /Kids [" .
        implode(
            " ",
            array_map(
                fn($id)=>"$id 0 R",
                $pageObjects
            )
        )
        ."]
        /Count ".count($pageObjects)."
        >>";



        // Fonts

        $this->objects[] =
        "<<
        /Type /Font
        /Subtype /Type1
        /BaseFont /Helvetica
        >>";


        $this->objects[] =
        "<<
        /Type /Font
        /Subtype /Type1
        /BaseFont /Helvetica-Bold
        >>";






        $pdf="%PDF-1.4\n";


        foreach($this->objects as $i=>$obj){

            $this->offsets[$i+1] =
                strlen($pdf);


            $pdf .=
                ($i+1)." 0 obj\n";


            $pdf .=
                $obj."\n";


            $pdf .=
                "endobj\n";
        }



        $xref =
            strlen($pdf);



        $pdf .=
        "xref\n";


        $pdf .=
        "0 ".(count($this->objects)+1)."\n";


        $pdf .=
        "0000000000 65535 f \n";



        foreach($this->offsets as $offset){

            $pdf .= sprintf(
                "%010d 00000 n \n",
                $offset
            );
        }



        $pdf .=
"trailer
<<
/Size ".(count($this->objects)+1)."
/Root 1 0 R
>>
startxref
$xref
%%EOF";



        header(
            "Content-Type: application/pdf"
        );


        header(
            "Content-Disposition: inline; filename=\"$filename\""
        );


        echo $pdf;

        exit;
    }



    /**
     * Build the canonical download filename: <MM-DD-YYYY>_<Module>_<Entity>.pdf
     * e.g. filename('RFQ', 'Apex Medical Solutions') => 07-23-2026_RFQ_Apex_Medical_Solutions.pdf
     *
     * Every non-alphanumeric run in the module/entity collapses to a single
     * underscore (spaces, punctuation, accents), so the result is always a safe,
     * consistent filename across modules.
     */
    public static function filename(string $module, string $entity): string
    {
        $slug = static fn(string $s): string =>
            trim(preg_replace('/[^A-Za-z0-9]+/', '_', $s), '_');

        $module = $slug($module) ?: 'Report';
        $entity = $slug($entity) ?: 'Unknown';

        return date('m-d-Y') . "_{$module}_{$entity}.pdf";
    }


    private function escape(string $text):string
    {
        return str_replace(
            ["\\","(",")"],
            ["\\\\","\\(","\\)"],
            $text
        );
    }
}