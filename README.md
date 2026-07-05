# VoidPanel WHMCS Server Provisioning Module

An official WHMCS server module that connects your billing panel to the **VoidPanel API v2** to automate web hosting accounts provisioning, suspension, termination, and service modifications.

---

## ⚡ Core Features

- **Automated Provisioning**: Instantly setups web hosting accounts on successful checkout payments.
- **Service Suspension/Unsuspension**: Automatically locks/unlocks hosting access due to payment status.
- **Account Termination**: Safely removes client user files, configurations, and databases on termination.
- **Quota & Package Upgrades**: Automatically adjusts Unix disk limits and quotas on package upgrades.
- **Credential Syncer**: Updates system passwords from WHMCS client profiles.
- **Client SSO log in**: Provides a direct "Open Control Panel" button inside the WHMCS Client Area for frictionless access.
- **Username Auto-Sync**: Captured control-panel generated username returns are saved back to WHMCS client fields.

---

## 📂 Directory Structure

Deploy the module directories as mapped below:

```text
[WHMCS ROOT]/
 └── modules/
      └── servers/
           └── voidpanel/
                ├── voidpanel.php   # Core provisioning functions
                └── logo.png        # Module display logo
```

---

## 🔧 Installation Guide

1. Clone this repository or download the latest release zip file.
2. Upload the `modules/servers/voidpanel/` directory to the `modules/servers/` directory of your active WHMCS installation.
3. Verify that the file permissions allow WHMCS to read `voidpanel.php` and `logo.png`.

---

## ⚙️ Setup Instructions

### 1. Register VoidPanel Server in WHMCS

1. Go to your **WHMCS Admin Area**.
2. Navigate to **System Settings > Servers**.
3. Click **Add New Server** and populate:
   - **Name**: `VoidPanel Main Node` (or preferred label)
   - **Hostname/IP Address**: Enter your panel server IP address or hostname.
   - **Module**: Select `Voidpanel` from the dropdown list.
   - **Password / API Token**: Paste your active **VoidPanel API Token** (obtained from VoidPanel Super Admin -> API Keys).
   - **Port**: Set to `8000` (VoidPanel default port).
   - **Secure**: Check the **Tick to use SSL (https)** box if you have configured SSL on the panel.
4. Click **Test Connection** to confirm authentication and connectivity, then click **Save**.

### 2. Configure Billing Products

1. Navigate to **System Settings > Products/Services**.
2. Select your hosting product and click the **Module Settings** tab.
3. Select **Voidpanel** as the *Module Name*.
4. Under **Hosting Package**, enter the **exact name** of the package configured inside VoidPanel (e.g. `default`, `basic`, `professional`). This field is case-sensitive.
5. Save changes.

---

## 🛡️ Recommended API Scopes

For server security isolation, create a dedicated API key inside VoidPanel Super Admin and configure it with only the following active scopes:

- [x] `accounts.create`
- [x] `accounts.suspend`
- [x] `accounts.unsuspend`
- [x] `accounts.terminate`
- [x] `accounts.change_package`
- [x] `accounts.change_password`
- [x] `server.status` (for test connection requests)

---

## ❔ Troubleshooting

### Connection Timeouts (CURL Errors)
- Check that outgoing connections from your WHMCS server IP to the VoidPanel server port (defaults to `8000`) are allowed by firewalls on both sides.
- On the VoidPanel node, ensure the CSF/UFW firewall allows inbound connections on port `8000`.

### "Package Not Found" Errors
- Check that the package name configured in the WHMCS Product -> Module Settings page matches a package name inside VoidPanel *exactly* (case-sensitive).

---

## 📄 License

This integration module is released under the **GPL-3.0 License**. Feel free to customize and redistribute. Developed by the VoidPanel community.
