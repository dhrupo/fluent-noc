# Office NOC Manager

A WordPress plugin for managing No Objection Certificate (NOC) requests with frontend application form, admin review panel, PDF generation, and email notifications.

## Features

- **Frontend Application Form**: Shortcode-based form with employee details, country selection, and date validation
- **Admin Panel**: Unified Gutenberg block-based interface for HR to review and manage all requests
- **Advanced Filtering**: Filter requests by status, date range, and search by name/email
- **PDF Generation**: Generate NOC certificates from Gutenberg block templates using Dompdf
- **PDF Customization**: Upload header and footer images for professional letterhead design
- **Email Notifications**: Automated emails for submission, approval (with PDF attachment), and rejection
- **Public Verification**: Public page to verify NOC status using alphanumeric reference ID
- **QR Code Support**: QR codes on PDFs for instant verification
- **Rate Limiting**: Built-in spam protection
- **Date Validation**: Prevents selecting past dates and ensures logical date ranges

## Installation

1. Upload the plugin files to `/wp-content/plugins/fluent-noc/`
2. Navigate to the plugin directory:
   ```bash
   cd /wp-content/plugins/fluent-noc/
   ```
3. Install Composer dependencies:
   ```bash
   composer install
   ```

4. Install npm dependencies:
   ```bash
   npm install
   ```

5. **If the above commands fail**, try using the setup script:
   ```bash
   ./setup.sh
   ```
   
   **Troubleshooting:**
   
   - **Composer SSL certificate errors**: Run `composer config -g secure-http false` then try again
   - **npm permission errors**: Run `sudo chown -R $(whoami) ~/.npm && npm cache clean --force` then try again

6. Build block assets (optional, for development):
   ```bash
   npm run build
   # or for development with watch mode:
   npm run start
   ```

7. Activate the plugin through the 'Plugins' menu in WordPress

## Requirements

- WordPress 6.4 or higher
- PHP 7.4 to 8.2
- Composer (for dependencies: dompdf, endroid/qr-code)
- Node.js and npm (optional, for block development)

## Usage

### Frontend Form

Use the shortcode `[noc_application_form]` on any page or post to display the application form.

### Admin Panel

1. Navigate to **Office NOC** in the WordPress admin menu
2. **NOC Requests**: Unified page to view all requests with advanced filtering:
   - Filter by status (All, Pending, Approved, Rejected)
   - Filter by date range (From/To dates)
   - Search by employee name or email
3. **PDF Template Designer**: Design your NOC certificate template using Gutenberg blocks
4. **Settings**: Configure company details, contact information, HR details, signature image, PDF header/footer images, and email settings

### PDF Template Design

1. Go to **Office NOC > PDF Template Designer**
2. Use standard WordPress blocks to design your template
3. Use placeholders like `{{full_name}}`, `{{reference_id}}`, etc.
4. Click "Save Template" to save your design
5. Use "Preview PDF" to see how it looks with sample data

### Application Form Fields

- **Full Name** (required)
- **Employee ID** (required)
- **Email** (required)
- **Joining Date** (required)
- **Position** (required)
- **Department** (required)
- **Visiting Country** (required)
- **Purpose of Visit** (required)
- **Leave Start Date** (required)
- **Leave End Date** (required)

### Available Placeholders

**Employee Information:**
- `{{full_name}}` - Employee full name
- `{{employee_id}}` - Employee ID
- `{{email}}` - Employee email
- `{{joining_date}}` - Employee joining date
- `{{position}}` - Employee position
- `{{department}}` - Employee department

**NOC Details:**
- `{{reference_id}}` - NOC reference ID (alphanumeric format: NOC2025A1B2C3D4)
- `{{visiting_country}}` - Destination country
- `{{purpose}}` - Purpose of visit
- `{{leave_start}}` - Leave start date
- `{{leave_end}}` - Leave end date
- `{{number_of_days}}` - Calculated number of days between leave dates
- `{{issue_date}}` - Current date

**Company Information:**
- `{{company_name}}` - Company name from settings
- `{{company_address}}` - Company address
- `{{company_phone}}` - Company phone number
- `{{company_email}}` - Company email address
- `{{hr_name}}` - HR Manager name
- `{{hr_title}}` - HR Manager title

**Images (for image blocks):**
- `{{qr_code}}` - QR code image for verification
- `{{signature}}` - HR signature image

### Verification

Public verification page is available at: `/noc-verification/?ref=NOC2025A1B2C3D4`

The reference ID is alphanumeric and non-guessable for security. Users can scan the QR code on the PDF or manually enter the reference ID to verify the NOC status.

## Settings

Configure the following in **Office NOC > Settings**:

- **Company Information**: Name, Address, Phone, Email
- **HR Details**: HR Manager Name and Title
- **HR Signature Image**: Upload signature image for PDF
- **PDF Header Image**: Upload office header image
- **PDF Footer Image**: Upload office footer image
- **Email Settings**: From Name and From Address

## Dependencies

- **Dompdf**: PDF generation library
- **endroid/qr-code**: QR code generation library

## Security

- Nonce validation on all form submissions
- Capability checks for admin functions
- Input sanitization and validation
- SQL prepared statements
- Rate limiting on form submissions
- XSS prevention in output

## File Structure

```
fluent-noc/
├── office-noc-manager.php          # Main plugin file
├── includes/                        # PHP classes
│   ├── class-admin.php             # Admin interface
│   ├── class-block-renderer.php   # Gutenberg block to HTML renderer
│   ├── class-db.php                # Database operations
│   ├── class-email.php             # Email notifications
│   ├── class-frontend-form.php    # Frontend form handler
│   ├── class-gutenberg-admin.php  # Gutenberg admin blocks
│   ├── class-pdf-generator.php    # PDF generation
│   ├── class-template-helper.php  # Template utilities
│   ├── class-verification.php     # Public verification page
│   └── country-names.php          # Country list
├── assets/                          # CSS and JavaScript
│   ├── css/                        # Stylesheets
│   └── js/                         # JavaScript files
└── vendor/                          # Composer dependencies
```

## Support

For issues and feature requests, please contact the plugin developer.

## License

GPL v2 or later

