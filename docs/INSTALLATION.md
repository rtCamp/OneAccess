# OneAccess - Installation and Setup Guide

This guide provides detailed instructions for installing and configuring OneAccess for your WordPress enterprise environment.

## Installation Overview

OneAccess requires installation on **two types of sites**: one **Governing Site** (central user management dashboard) and multiple **Brand Sites** (managed sites).

## Step 1: Download and Install Plugin

1. Download the latest OneAccess plugin from [GitHub Releases](https://github.com/rtCamp/OneAccess/releases)  
2. Upload the plugin files to /wp-content/plugins/oneaccess/ on both governing and brand sites  
3. If installing from source code, run the following commands in the plugin directory:

```shell
composer install && npm install && npm run build:prod
```

## Step 2: Setup Governing Site (Central User Management Dashboard)

**The governing site acts as your central control panel to manage users across all brand sites.**

1. **Activate Plugin:** Go to WordPress Admin → Plugins and activate OneAccess  
2. **Configure Site Type:** Upon activation, select **"Governing Site"** when prompted  
3. **Verify Access:** Navigate to OneAccess → User Manager to access the centralized dashboard  
4. **Check User Role:** Ensure your account has the **Network Admin** role (automatically created)

## Step 3: Setup Brand Sites (Managed Sites)

**Each brand site needs OneAccess installed to receive user management commands.**

1. **Activate Plugin:** Go to WordPress Admin → Plugins and activate OneAccess on each brand site  
2. **Configure Site Type:** Upon activation, select **"Brand Site"** when prompted  
3. **Generate API Key:** The plugin will generate a unique API key for secure communication  
4. **Copy Configuration Details:** Note down:  
   - Site Name  
   - Site URL  
   - API Key

## Step 4: Connect Brand Sites to Governing Site

**Register each brand site with your governing site for centralized user management.**

1. **Access Governing Site:** Go to OneAccess → Settings on your governing site  
2. **Add Brand Site:** Click "Add New Site" and enter:  
   - **Site Name:** Descriptive name for the brand site  
   - **Site URL:** Full URL of the brand site  
   - **API Key:** The API key generated on the brand site

## Configuration Verification

After completing the installation:

1. **Test Connection:** Verify that brand sites appear in the governing site's dashboard  
2. **Check API Communication:** Ensure the governing site can communicate with all brand sites  
3. **Test User Creation:** Try creating a test user and assigning to brand sites

## Troubleshooting Installation

### Common Installation Issues

#### Users Not Appearing on Brand Sites

- Verify API key configuration on brand sites  
- Check network connectivity between sites  
- Confirm REST API permissions

#### Profile Requests Not Working

- Verify nonce validation is working correctly  
- Check API key permissions and configuration  
- Confirm user roles and capabilities  
- Review WordPress REST API functionality

#### Connection Issues Between Sites

- Check API key errors \- regenerate and reconfigure API keys  
- Verify network connectivity between sites  
- Ensure WordPress REST API is enabled and accessible  
- Check SSL/HTTPS configuration if using secure connections

### Getting Help

If you encounter issues during installation:

- **Issues & Bug Reports:** [GitHub Issues](https://github.com/rtCamp/OneAccess/issues)  
- **Feature Requests:** [GitHub Discussions](https://github.com/rtCamp/OneAccess/discussions)  
- **Documentation:** [Project Wiki](https://github.com/rtCamp/OneAccess/wiki)

## Next Steps

Once installation is complete, refer to the [main README](../README.md) for:

- Usage instructions  
- User management features  
- Advanced configuration options

---

**Need additional help?** Visit our [GitHub repository](https://github.com/rtCamp/OneAccess) for the latest updates and community support.  
