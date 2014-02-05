civicrm-relationshipEventACL-module
===================================

[CiviCRM] (https://civicrm.org/) module to use contact relationships edit rights to determine event visibility and editability in CiviCRM administration screens.

This module uses relationships intead of groups or ACL to limit visibility and editability. The whole relationship tree is searched and all events that are owned by contacts to where user has edit permissions through relationships are made visible and editable. All contact types are searched.

This module requires a custom field to event that specifies event owner contact ID.

Portions of this module is based on the idea of [Relationship Permissions as ACLs] (https://civicrm.org/extensions/relationship-permissions-acls) extension. This module includes code from [relationshipACL](https://github.com/anttikekki/civicrm-relationshipACL-module) module.

### Example
* Organisation 1
* Sub-organisation 1 (Organisation 1 has edit relationship to this organisation)
* Sub-organisation 2 (Sub-organisation 1 has edit relationship to this organisation)
* User 1 (has edit relationship to Sub-organisation 1)

Events
* Event 1. Owned by Organisation 1.
* Event 2. Owned by Sub-organisation 2.

With this module User 1 can see and edit Event 2 but not Event 1. Event 2 is owned by Sub-organisation 2 that User 1 has edit rights. User 1 does not have edit rights to Organisation 1 so this event is invisible to user.

### Current implementation status
This module filters search results rows on `Event Dashboard`, `Manage Events` and `Find participants` pages. It also prevents user from accessing Event edit page by URL.

### Installation
1. Copy _com.github.anttikekki.relationshipEventACL_ folder to CiviCRM extension folder and enable extension in administration.
2. Insert row to this module configuration table `civicrm_relationshipeventacl_config`. `config_key` column value is `eventOwnerCustomGroupName` and `congif_value` column value is Event custom field group title name that stores owner contact info.
3. Rebuild navigation menu. Go to Administer -> System Settings -> Cleanup Caches and Update Paths and push `Cleanup caches`

This module uses temporary tables in database so CiviCRM MySQL user has to have permissions to create these kind of tables.

### Performance considerations
This module performance on large CiviCRM sites may be poor. Module needs to determine the relationship tree structure on every administration event pageload. The relationship tree may be deep and complex. This means 1 query for every relationship level. The search done with help of temporary table in database.

This logic may lead to multiple large queries and large temporary table on every event page load in contact administration.

### Licence
GNU Affero General Public License
