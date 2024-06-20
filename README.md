# User Session Management

The "User Session Management"-plugin is a UIHook-Plugin for ILIAS that allows
you to restrict multiple logins by one user:
* It stops users from login twice showing a corresponding message.
* It shows a tab in the object "Course" where administrators of the corresponding
course can see all logged in members of the course and allow them to selectively
to relogin.

**Minimum ILIAS Version:**
9.0

**Maximum ILIAS Version:**
9.99

**Responsible Developer:**
Stephan Kergomard - office@kergomard.ch
**Supported Languages:**
German, English

### Quick Installation Guide
1. Copy the content of this folder into
<ILIAS_directory>/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/UserSessionManagement
or clon this Github-Repo to
<ILIAS_directory>/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/

2. Run `composer du` on your installation.

2. Access ILIAS, go to the administration menu and select "Plugins".

3. Look for the UserSessionManagement-Plugin in the table and "Install" the plugin
from the corresponding "Action"-dropdown on the right.

4. Select "Configure" from the same dropdown to choose your settings.

5. Select "Activate" from the same dropdown to activate the plugin.