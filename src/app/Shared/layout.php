<?php
/**
 * Shared page-layout helpers.
 *
 * Every page used to hand-include header.php + sidebar.php … footer.php (and the
 * 403 flow re-assembled all four inline). These helpers give the whole app one
 * way to open/close the page chrome and one way to render a page header bar, so
 * modules stop inventing their own vocabulary.
 *
 * Usage (entry point):
 *   layout_open('Customers');
 *   include '.../views/accounts_list.php';   // or a controller that echoes markup
 *   layout_close();
 *
 * Permission-denied flow:
 *   if (!Permissions::can('rfqs.view')) { layout_deny(); exit; }
 *
 * Inside a view, for the header bar:
 *   page_header('Customer Accounts', ['href' => 'create_account.php', 'label' => '+',
 *               'class' => 'add-btn', 'title' => 'Add a new customer']);
 *
 * Loaded once by Core/bootstrap.php, so these functions are always available.
 */

if (!function_exists('layout_open')) {

    /** Emit the document head + sidebar chrome. $title suffixes the browser tab. */
    function layout_open(string $title = ''): void
    {
        $layoutTitle = $title;              // read by header.php's <title>
        include __DIR__ . '/header.php';
        include __DIR__ . '/sidebar.php';
    }

    /** Close the page chrome (footer + scripts). */
    function layout_close(): void
    {
        include __DIR__ . '/footer.php';
    }

    /**
     * Full permission-denied page: sets the status code and renders the shared
     * 403 view inside the normal chrome. Caller should `exit;` afterwards.
     */
    function layout_deny(int $code = 403): void
    {
        http_response_code($code);
        layout_open('Permission Denied');
        include __DIR__ . '/error_403.php';
        layout_close();
    }

    /**
     * Render the canonical `.page-header` bar: an <h1> and an optional action link.
     *
     * @param array|null $action ['href' => (required), 'label' => '+',
     *                            'class' => 'btn btn-primary', 'title' => '']
     */
    function page_header(string $title, ?array $action = null): void
    {
        echo '<div class="page-header">';
        echo '<h1>' . htmlspecialchars($title) . '</h1>';

        if ($action !== null && !empty($action['href'])) {
            $cls   = $action['class'] ?? 'btn btn-primary';
            $label = $action['label'] ?? '+';
            $attrs = ' href="' . htmlspecialchars($action['href']) . '" class="' . htmlspecialchars($cls) . '"';
            if (!empty($action['title'])) {
                $attrs .= ' title="' . htmlspecialchars($action['title']) . '"';
            }
            echo '<a' . $attrs . '>' . htmlspecialchars($label) . '</a>';
        }

        echo '</div>';
    }
}
