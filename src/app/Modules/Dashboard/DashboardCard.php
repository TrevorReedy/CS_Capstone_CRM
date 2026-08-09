<?php
namespace App\Modules\Dashboard;

use App\Core\Permissions;

/**
 * Base class for every dashboard card.
 *
 * A card is a self-contained unit that appears on the dashboard grid. Each card
 * declares a title, an optional permission (so it only shows for roles that may
 * see it), and a body. The body is built with one of the two shared renderers:
 *
 *   - stat()    — a single big number for "how many" questions.
 *   - preview() — a truncated top-N list + a deep link for "which ones" questions.
 *
 * To add a card: extend this class, implement title() + body(), optionally set
 * permission(), then register it in DashboardController::cards().
 */
abstract class DashboardCard
{
    public function __construct(protected DashboardService $service) {}

    /** Heading shown at the top of the card. */
    abstract public function title(): string;

    /** Inner HTML — build it with stat() or preview(). */
    abstract public function body(): string;

    /**
     * Permission key required to see this card. Return null for "always visible".
     * Uses the same keys as the rest of the app (e.g. 'rfqs.view').
     */
    public function permission(): ?string
    {
        return null;
    }

    /** Whether the current user is allowed to see this card. */
    public function visible(): bool
    {
        $perm = $this->permission();
        return $perm === null || Permissions::can($perm);
    }

    /** Full card markup: shell + title + body. */
    public function render(): string
    {
        return '<div class="dash-card">'
             . '<h3 class="dash-card-title">' . htmlspecialchars($this->title()) . '</h3>'
             . $this->body()
             . '</div>';
    }

    // ── Shared body renderers ─────────────────────────────────────────────────

    /**
     * A big single number with an optional sub-line and deep link.
     * Use for stat cards ("Active RFQs: 12").
     */
    protected function stat(int|string $value, string $sub = '', string $link = '', string $linkLabel = 'View all'): string
    {
        $html = '<p class="dash-stat">' . htmlspecialchars((string)$value) . '</p>';
        if ($sub !== '') {
            $html .= '<p class="dash-stat-sub text-muted">' . htmlspecialchars($sub) . '</p>';
        }
        if ($link !== '') {
            $html .= $this->deepLink($link, $linkLabel);
        }
        return $html;
    }

    /**
     * A truncated top-N list with a deep link to the full module page.
     * Use for preview cards ("Low Stock (4)" → 4 worst items → View inventory).
     *
     * @param array $rows Each row: [
     *     'label'       => string,          // primary text (required)
     *     'meta'        => string,          // muted secondary text (optional)
     *     'date'        => string,          // muted date line (optional)
     *     'badge'       => string,          // badge text (optional)
     *     'badge_class' => string,          // rfq-badge-* class (optional)
     *     'href'        => string,          // makes the label a deep link (optional)
     * ]
     */
    protected function preview(array $rows, string $link = '', string $linkLabel = 'View all'): string
    {
        if (empty($rows)) {
            $html = '<p class="dash-empty text-muted">Nothing to show.</p>';
        } else {
            $html = '<ul class="dash-list">';

            foreach ($rows as $row) {
                $html .= '<li class="dash-list-row">';

                // Left side: interaction details
                $html .= '<div class="dash-list-content">';

                $html .= '<div class="dash-list-label">';
                if (!empty($row['href'])) {
                    $html .= '<a class="dash-list-link" href="' . htmlspecialchars($row['href']) . '">'
                           . htmlspecialchars($row['label'] ?? '') . '</a>';
                } else {
                    $html .= htmlspecialchars($row['label'] ?? '');
                }
                $html .= '</div>';

                if (!empty($row['meta'])) {
                    $html .= '<div class="dash-list-meta text-muted">';
                    $html .= htmlspecialchars($row['meta']);
                    $html .= '</div>';
                }

                if (!empty($row['date'])) {
                    $html .= '<div class="dash-list-date text-muted">';
                    $html .= htmlspecialchars($row['date']);
                    $html .= '</div>';
                }

                $html .= '</div>';

                // Right side: badge
                if (!empty($row['badge'])) {
                    $cls = htmlspecialchars($row['badge_class'] ?? 'rfq-badge-neutral');

                    $html .= '<span class="rfq-badge ' . $cls . '">';
                    $html .= htmlspecialchars($row['badge']);
                    $html .= '</span>';
                }

                $html .= '</li>';
            }

            $html .= '</ul>';
        }

        if ($link !== '') {
            $html .= $this->deepLink($link, $linkLabel);
        }

        return $html;
    }

    protected function deepLink(string $href, string $label = 'View all'): string
    {
        return '<a class="dash-card-link" href="' . htmlspecialchars($href) . '">'
             . htmlspecialchars($label) . ' &rarr;</a>';
    }

    /** Maps an RFQ pipeline stage to its rfq-badge-* CSS class (shared by RFQ cards). */
    protected function stageBadgeClass(string $stage): string
    {
        return [
            'New'         => 'rfq-badge-neutral',
            'In Review'   => 'rfq-badge-info',
            'Quoted'      => 'rfq-badge-quoted',
            'Negotiation' => 'rfq-badge-warning',
            'Won'         => 'rfq-badge-success',
            'Lost'        => 'rfq-badge-danger',
        ][$stage] ?? 'rfq-badge-neutral';
    }

    /** Maps a campaign status to its rfq-badge-* CSS class (shared by campaign cards). */
    protected function campaignStatusBadgeClass(string $status): string
    {
        return [
            'Draft'     => 'rfq-badge-neutral',
            'Scheduled' => 'rfq-badge-info',
            'Sent'      => 'rfq-badge-quoted',
            'Completed' => 'rfq-badge-success',
        ][$status] ?? 'rfq-badge-neutral';
    }

    /** Compact money formatting for stat/preview values: 1234.5 → "$1,235". */
    protected function money(float|int|string $amount): string
    {
        return '$' . number_format((float)$amount);
    }
}
