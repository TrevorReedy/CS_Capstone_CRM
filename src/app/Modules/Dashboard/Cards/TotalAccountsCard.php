<?php
namespace App\Modules\Dashboard\Cards;

use App\Modules\Dashboard\DashboardCard;
use App\Modules\Customer\CustomerRepository;

/**
 * Stat card — total number of accounts, with a contact-count sub-line.
 */
class TotalAccountsCard extends DashboardCard
{
    public function title(): string { return 'Total Accounts'; }

    public function permission(): ?string { return 'customers.view'; }

    public function body(): string
    {
        $repo = new CustomerRepository();

        $accounts = $repo->searchCount();   // COUNT(*) FROM accounts (no filters)
        $contacts = $repo->contactCount();

        return $this->stat(
            $accounts,
            number_format($contacts) . ' contact' . ($contacts === 1 ? '' : 's'),
            '/modules/customer/accounts.php',
            'View accounts'
        );
    }
}
