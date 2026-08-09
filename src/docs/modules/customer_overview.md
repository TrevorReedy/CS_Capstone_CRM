app/Modules/Customer/

The Customer module owns:

customer profile creation and maintenance
contact management
interaction history tracking

Recommended structure:

Customer/
├── CustomerController.php
├── CustomerService.php
├── CustomerRepository.php
├── customer_routes.php
└── views/
    ├── accounts_list.php
    ├── account_detail.php
    ├── contact_form.php
    └── interactions.php
Customer pages
Accounts List
Account Detail
Contact Detail / Contact Panel
Interaction History
Import Accounts / Contacts
Customer database ownership

Max’s module mainly works with:

accounts
contacts
interactions
account_tags
tags
Customer module flow

Example: User opens an account detail page.

account_detail.php
        ↓
CustomerController receives account ID
        ↓
CustomerService checks account rules
        ↓
CustomerRepository gets account, contacts, and interactions
        ↓
View renders account_detail.php