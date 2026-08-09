app/Modules/Admin/

The Admin module is also shared.

It owns:

users
roles
permissions
settings

Recommended structure:

Admin/
├── AdminController.php
├── UserService.php
├── UserRepository.php
└── views/
    ├── users.php
    ├── roles.php
    └── settings.php
Admin pages
Users
Roles
Settings
Permission Management
Admin database ownership

The Admin module mainly works with:

users
roles
permissions
Admin module flow

Example: Super Admin updates a user role.

users.php
        ↓
require_auth.php checks login
        ↓
Permissions::require('admin.manage_users')
        ↓
AdminController receives form
        ↓
UserService validates role change
        ↓
UserRepository updates users table
        ↓
View reloads users.php