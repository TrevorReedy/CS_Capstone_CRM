<?php
namespace App\Modules\Admin;

use App\Core\Auth;

class AdminController
{
    private AdminRepository $repo;
    private UserRepository  $userRepo;
    private UserService     $userService;

    private array $userErrors = [];
    private array $userInput  = ['name' => '', 'email' => '', 'role_id' => '', 'password' => ''];

    public function __construct()
    {
        $this->repo        = new AdminRepository();
        $this->userRepo    = new UserRepository();
        $this->userService = new UserService($this->userRepo);
    }

    // ── Entry dispatch ────────────────────────────────────────────────────────

    public function dispatch(): void
    {
        $action = $_GET['action'] ?? 'list';
        $id     = (int)($_GET['id'] ?? 0);

        match ($action) {
            'create' => $this->createUser(),
            'edit'   => $this->editUser($id),
            default  => $this->listUsers(),
        };
    }

    // ── User List ─────────────────────────────────────────────────────────────

    public function listUsers(): void
    {
        // The users list is now a client-driven DataTable (server-side
        // processing). This just renders the shell; rows are fetched from
        // /admin/users_data.php.
        include __DIR__ . '/views/users.php';
    }

    // ── Create User ───────────────────────────────────────────────────────────

    public function handleCreateUserPost(): void
    {
        $this->userInput = [
            'name'     => trim($_POST['name']    ?? ''),
            'email'    => trim($_POST['email']   ?? ''),
            'password' => $_POST['password']     ?? '',
            'role_id'  => trim($_POST['role_id'] ?? ''),
        ];

        $this->userErrors = $this->userService->validateUserInput($this->userInput, false);

        if (empty($this->userErrors) && $this->userRepo->emailExists($this->userInput['email'])) {
            $this->userErrors[] = 'A user with that email already exists.';
        }

        if (empty($this->userErrors)) {
            $this->userService->createUser($this->userInput);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'User created successfully.'];
            header('Location: /admin/users.php');
            exit;
        }

        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Please fix the errors below.'];
    }

    public function createUser(): void
    {
        $roles  = $this->repo->allStandardRoles();
        $errors = $this->userErrors;
        $input  = $this->userInput;
        $user   = null;
        include __DIR__ . '/views/user_form.php';
    }

    // ── Edit User ─────────────────────────────────────────────────────────────

    public function handleEditUserPost(int $id): void
    {
        $this->userInput = [
            'name'     => trim($_POST['name']    ?? ''),
            'email'    => trim($_POST['email']   ?? ''),
            'password' => $_POST['password']     ?? '',
            'role_id'  => trim($_POST['role_id'] ?? ''),
        ];

        $this->userErrors = $this->userService->validateUserInput($this->userInput, true);

        if (empty($this->userErrors) && $this->userRepo->emailExists($this->userInput['email'], $id)) {
            $this->userErrors[] = 'A user with that email already exists.';
        }

        if (empty($this->userErrors)) {
            $this->userService->updateUser($id, $this->userInput);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'User updated successfully.'];
            header('Location: /admin/users.php');
            exit;
        }

        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Please fix the errors below.'];
    }

    public function editUser(int $id): void
    {
        $user = $this->userRepo->findById($id);
        if (!$user) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'User not found.'];
            header('Location: /admin/users.php');
            exit;
        }

        $roles  = $this->repo->allStandardRoles();
        $errors = $this->userErrors;
        $input  = $this->userErrors ? $this->userInput : [
            'name'     => $user['name'],
            'email'    => $user['email'],
            'role_id'  => $user['role_id'],
            'password' => '',
        ];
        include __DIR__ . '/views/user_form.php';
    }

    // ── Delete User ───────────────────────────────────────────────────────────

    public function handleDeleteUserPost(int $id): void
    {
        $currentUserId = (int)(Auth::user()['id'] ?? 0);
        if ($id === $currentUserId) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'You cannot delete your own account.'];
            header('Location: /admin/users.php');
            exit;
        }

        $user = $this->userRepo->findById($id);
        if (!$user) {
            header('Location: /admin/users.php');
            exit;
        }

        $roleId = (int)$user['role_id'];

        // Delete the user first so the FK on users.role_id no longer blocks role deletion.
        $this->userRepo->delete($id);

        // If they had a custom role, clean it up.
        // deleteRole() has an owner_user_id IS NOT NULL guard, so system roles are safe.
        if (!empty($user['owner_user_id'])) {
            $this->repo->deleteRole($roleId);
        }

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'User deleted.'];
        header('Location: /admin/users.php');
        exit;
    }

    // ── Custom Role ───────────────────────────────────────────────────────────

    public function handleCreateCustomRolePost(): void
    {
        $userId = (int)($_POST['user_id'] ?? 0);
        $user   = $this->userRepo->findById($userId);

        if (!$user) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'User not found.'];
            header('Location: /admin/users.php');
            exit;
        }

        if (!empty($user['owner_user_id'])) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'User already has a custom role.'];
            header('Location: /admin/users.php?action=edit&id=' . $userId);
            exit;
        }

        // Remove any orphaned custom role for this user (leftover from a prior failed attempt)
        // before inserting — roles.role_name has a UNIQUE constraint that would otherwise crash.
        $this->repo->deleteOrphanedCustomRoleForUser($userId);

        try {
            $roleName = 'Custom — ' . $user['name'];
            $roleId   = $this->repo->insertRole($roleName, 'Custom role scoped to ' . $user['name'], $userId);

            $this->userRepo->update($userId, [
                'name'    => $user['name'],
                'email'   => $user['email'],
                'role_id' => $roleId,
            ]);
        } catch (\Throwable $e) {
            // Was concatenating the raw message, which surfaced PDO/SQL text in
            // the UI. Log it, show the user a reference instead.
            $_SESSION['flash'] = [
                'type'    => 'error',
                'message' => \App\Core\ErrorResponse::flash($e, 'create the custom role', 'AdminController::assignCustomRole'),
            ];
            header('Location: /admin/users.php?action=edit&id=' . $userId);
            exit;
        }

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Custom role created. Set its permissions below.'];
        header('Location: /admin/permissions.php');
        exit;
    }

    public function handleRemoveCustomRolePost(): void
    {
        $userId       = (int)($_POST['user_id']  ?? 0);
        $newRoleId    = (int)($_POST['role_id']   ?? 0);
        $user         = $this->userRepo->findById($userId);

        if (!$user || !$newRoleId) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid request.'];
            header('Location: /admin/users.php');
            exit;
        }

        $oldRoleId = (int)$user['role_id'];

        // Reassign to standard role first so the FK is satisfied before deleting the old role.
        $this->userRepo->update($userId, [
            'name'    => $user['name'],
            'email'   => $user['email'],
            'role_id' => $newRoleId,
        ]);

        $this->repo->deleteRole($oldRoleId);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Custom role removed.'];
        header('Location: /admin/users.php?action=edit&id=' . $userId);
        exit;
    }

    // ── Permission Matrix ─────────────────────────────────────────────────────

    public function permissionMatrix(): void
    {
        $roles    = $this->repo->allRoles();
        $granted  = $this->repo->rolePermissionsMap();
        $allPerms = $this->buildPermissionList();

        include __DIR__ . '/views/permission_matrix.php';
    }

    public function handleSaveMatrixPost(): void
    {
        $matrix = [];
        foreach ($_POST['permissions'] ?? [] as $roleId => $perms) {
            $matrix[(int)$roleId] = array_values(array_unique((array)$perms));
        }
        $this->repo->savePermissions($matrix);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Permission matrix saved.'];
        header('Location: /admin/permissions.php');
        exit;
    }

    // ── Canonical permission list ─────────────────────────────────────────────

    private function buildPermissionList(): array
    {
        return [
            // Dashboard
            ['module' => 'Dashboard',    'action' => 'dashboard.view',              'label' => 'View Dashboard'],
            // Customers
            ['module' => 'Customers',    'action' => 'customers.view',              'label' => 'View Customers'],
            ['module' => 'Customers',    'action' => 'customers.create',            'label' => 'Create Customers'],
            ['module' => 'Customers',    'action' => 'customers.edit',              'label' => 'Edit Customers'],
            ['module' => 'Customers',    'action' => 'customers.delete',            'label' => 'Delete Customers'],
            // Contacts
            ['module' => 'Contacts',     'action' => 'contacts.view',               'label' => 'View Contacts'],
            ['module' => 'Contacts',     'action' => 'contacts.create',             'label' => 'Create Contacts'],
            ['module' => 'Contacts',     'action' => 'contacts.edit',               'label' => 'Edit Contacts'],
            ['module' => 'Contacts',     'action' => 'contacts.delete',             'label' => 'Delete Contacts'],
            // Interactions
            ['module' => 'Interactions', 'action' => 'interactions.view',           'label' => 'View Interactions'],
            ['module' => 'Interactions', 'action' => 'interactions.create',         'label' => 'Log Interactions'],
            ['module' => 'Interactions', 'action' => 'interactions.edit',           'label' => 'Edit Interactions'],
            ['module' => 'Interactions', 'action' => 'interactions.delete',         'label' => 'Delete Interactions'],
            // RFQ
            ['module' => 'RFQ',          'action' => 'rfqs.view',                   'label' => 'View RFQs'],
            ['module' => 'RFQ',          'action' => 'rfqs.create',                 'label' => 'Create RFQs'],
            ['module' => 'RFQ',          'action' => 'rfqs.edit',                   'label' => 'Edit RFQs'],
            ['module' => 'RFQ',          'action' => 'rfqs.delete',                 'label' => 'Delete RFQs'],
            ['module' => 'RFQ',          'action' => 'rfqs.update_stage',           'label' => 'Change Stage'],
            // Quotes
            ['module' => 'Quotes',       'action' => 'quotes.view',                 'label' => 'View Quotes'],
            ['module' => 'Quotes',       'action' => 'quotes.create',               'label' => 'Create Quotes'],
            ['module' => 'Quotes',       'action' => 'quotes.edit',                 'label' => 'Edit Quotes'],
            ['module' => 'Quotes',       'action' => 'quotes.delete',               'label' => 'Delete Quotes'],
            // Reservations
            ['module' => 'Reservations', 'action' => 'reservations.view',           'label' => 'View Reservations'],
            ['module' => 'Reservations', 'action' => 'reservations.create',         'label' => 'Create Reservations'],
            ['module' => 'Reservations', 'action' => 'reservations.update_status',  'label' => 'Update Reservation Status'],
            // Campaigns
            ['module' => 'Campaigns',    'action' => 'campaigns.view',              'label' => 'View Campaigns'],
            ['module' => 'Campaigns',    'action' => 'campaigns.create',            'label' => 'Create Campaigns'],
            ['module' => 'Campaigns',    'action' => 'campaigns.edit',              'label' => 'Edit Campaigns'],
            ['module' => 'Campaigns',    'action' => 'campaigns.delete',            'label' => 'Delete Campaigns'],
            // TODO: rename label to 'Simulate Send' — campaigns.metrics only gates handleSimulatePost(), metrics viewing is open to anyone with campaigns.view
            ['module' => 'Campaigns',    'action' => 'campaigns.metrics',           'label' => 'Simulate & View Metrics'],
            // Inventory
            ['module' => 'Inventory',    'action' => 'inventory.view',              'label' => 'View Inventory'],
            ['module' => 'Inventory',    'action' => 'inventory.create',            'label' => 'Create Products'],
            ['module' => 'Inventory',    'action' => 'inventory.edit',              'label' => 'Edit Products'],
            ['module' => 'Inventory',    'action' => 'inventory.update_stock',      'label' => 'Update Stock Levels'],
            ['module' => 'Inventory',    'action' => 'inventory.reserve',           'label' => 'Reserve Inventory'],
            // Reports
            ['module' => 'Reports',      'action' => 'reports.view',                'label' => 'View Reports'],
            // References
            ['module' => 'References',   'action' => 'references.view',             'label' => 'View References'],
            // Admin
            ['module' => 'Admin',        'action' => 'admin.manage_users',          'label' => 'Manage Users'],
            ['module' => 'Admin',        'action' => 'admin.manage_permissions',    'label' => 'Manage Roles & Permissions'],
        ];
    }
}
