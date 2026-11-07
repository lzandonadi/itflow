<?php

/*
 * ITFlow - Export helpers
 *
 * Shared plumbing behind the list page export modals:
 *   - a per-export column registry (label, source field, formatting, default state)
 *   - the column picker that every export modal renders
 *   - the CSV and PDF writers
 *
 * Handlers keep owning their own filter parsing and SQL. They hand rows over one
 * at a time, in the same loop the old fputcsv() call sat in:
 *
 *     $export = beginExport('assets', $_POST['export_assets'], "$file_name_prepend" . 'Assets', 'Assets', $filter_summary);
 *     while ($row = mysqli_fetch_assoc($sql)) {
 *         addExportRow($export, $row);
 *     }
 *     finishExport($export);
 *
 * A column's 'field' is just a key on the row array handed to addExportRow(), so a
 * handler that needs a computed value (a prefixed number, a decrypted secret, a
 * status word) sets that key on $row before the call - see getExportColumns() notes.
 */

// PDF is a read-and-print format, not a bulk transport. Past this many rows TCPDF's
// HTML table builder gets slow enough to walk into max_execution_time, so the
// handlers bounce the request back to CSV instead of timing out mid-download.
DEFINE("EXPORT_PDF_MAX_ROWS", 2000);

/*
 * The column registry - one entry per export, in the order columns should appear.
 *
 *   'label'   Column heading in the CSV / PDF.
 *   'field'   Key on the row array. Defaults to the column key itself.
 *   'format'  '' (raw), 'phone', 'money', 'number'. Only affects presentation.
 *   'default' false to leave the box unticked when the modal opens. Defaults true.
 *   'weight'  Relative PDF column width, default 1. Bump it for long free text.
 *
 * Column keys are what the modal posts back, so they are whitelisted against this
 * registry on the way in - an unknown key is dropped, not queried.
 */
function getExportColumns($export_type) {

    $registry = [

        // Clients - handler joins the primary contact and primary location
        'clients' => [
            'client_name'          => ['label' => __('Client Name'), 'weight' => 2],
            'client_type'          => ['label' => __('Industry')],
            'client_referral'      => ['label' => __('Referral')],
            'client_website'       => ['label' => __('Website'), 'weight' => 2],
            'location_name'        => ['label' => __('Primary Location Name')],
            'location_phone'       => ['label' => __('Location Phone'), 'format' => 'phone'],
            'location_address'     => ['label' => __('Location Address'), 'weight' => 2],
            'location_city'        => ['label' => __('City')],
            'location_state'       => ['label' => __('State')],
            'location_zip'         => ['label' => __('Postal Code')],
            'location_country'     => ['label' => __('Country')],
            'contact_name'         => ['label' => __('Primary Contact Name')],
            'contact_title'        => ['label' => __('Title')],
            'contact_phone'        => ['label' => __('Contact Phone'), 'format' => 'phone'],
            'contact_extension'    => ['label' => __('Extension')],
            'contact_mobile'       => ['label' => __('Contact Mobile'), 'format' => 'phone'],
            'contact_email'        => ['label' => __('Contact Email'), 'weight' => 2],
            'client_currency_code' => ['label' => __('Currency')],
            'client_tax_id_number' => ['label' => __('Tax ID')],
            'client_tax_rate'      => ['label' => __('Tax Rate'), 'default' => false],
            'client_tax_exempt_display' => ['label' => __('Tax Exempt'), 'default' => false],
            'client_tax_exempt_number' => ['label' => __('Exemption Certificate'), 'default' => false],
            'client_tax_exempt_expires' => ['label' => __('Exemption Expires'), 'default' => false],
            'client_abbreviation'  => ['label' => __('Abbreviation')],
        ],

        'contacts' => [
            'contact_name'       => ['label' => __('Name'), 'weight' => 2],
            'contact_title'      => ['label' => __('Title')],
            'contact_department' => ['label' => __('Department')],
            'contact_email'      => ['label' => __('Email'), 'weight' => 2],
            'contact_phone'      => ['label' => __('Phone'), 'format' => 'phone'],
            'contact_extension'  => ['label' => __('Ext')],
            'contact_mobile'     => ['label' => __('Mobile'), 'format' => 'phone'],
            'location_name'      => ['label' => __('Location')],
        ],

        // location_primary_text, contact_name and the counts are built by the handler's query
        'locations' => [
            'location_name'        => ['label' => __('Name'), 'weight' => 2],
            'location_primary_text' => ['label' => __('Primary')],
            'location_description' => ['label' => __('Description'), 'weight' => 3],
            'location_address'     => ['label' => __('Address'), 'weight' => 2],
            'location_city'        => ['label' => __('City')],
            'location_state'       => ['label' => __('State')],
            'location_zip'         => ['label' => __('Postal Code')],
            'location_country'     => ['label' => __('Country'), 'default' => false],
            'location_phone'       => ['label' => __('Phone'), 'format' => 'phone'],
            'location_hours'       => ['label' => __('Hours')],
            'contact_name'         => ['label' => __('On-site Contact')],
            'contact_count'        => ['label' => __('People'), 'format' => 'number', 'default' => false],
            'asset_count'          => ['label' => __('Assets'), 'format' => 'number', 'default' => false],
            'location_notes'       => ['label' => __('Access Notes'), 'weight' => 3, 'default' => false],
            'client_name'          => ['label' => __('Client'), 'default' => false],
        ],

        'vendors' => [
            'vendor_name'           => ['label' => __('Name'), 'weight' => 2],
            'vendor_description'    => ['label' => __('Description'), 'weight' => 3],
            'vendor_tags'           => ['label' => __('Tags'), 'default' => false],
            'vendor_phone'          => ['label' => __('Support Phone'), 'format' => 'phone'],
            'vendor_extension'      => ['label' => __('Extension'), 'default' => false],
            'vendor_email'          => ['label' => __('Support Email'), 'weight' => 2],
            'vendor_website'        => ['label' => __('Website'), 'weight' => 2],
            'vendor_status_page'    => ['label' => __('Status Page'), 'weight' => 2, 'default' => false],
            'vendor_portal'         => ['label' => __('Support Portal'), 'weight' => 2, 'default' => false],
            'vendor_hours'          => ['label' => __('Hours')],
            'vendor_sla'            => ['label' => __('SLA'), 'default' => false],
            'vendor_account_number' => ['label' => __('Account Number')],
            'vendor_code'           => ['label' => __('PIN / Code'), 'default' => false],
            'vendor_contact_name'   => ['label' => __('Account Manager')],
            'vendor_contact_phone'  => ['label' => __('Account Manager Phone'), 'format' => 'phone', 'default' => false],
            'vendor_contact_email'  => ['label' => __('Account Manager Email'), 'default' => false],
            'open_case_count'       => ['label' => __('Open Cases'), 'format' => 'number', 'default' => false],
            'vendor_notes'          => ['label' => __('Notes'), 'weight' => 3],
            'client_name'           => ['label' => __('Client'), 'default' => false],
        ],

        'assets' => [
            'asset_name'              => ['label' => __('Name'), 'weight' => 2],
            'asset_description'       => ['label' => __('Description'), 'weight' => 3],
            'asset_type'              => ['label' => __('Type')],
            'asset_make'              => ['label' => __('Make')],
            'asset_model'             => ['label' => __('Model')],
            'asset_serial'            => ['label' => __('Serial Number')],
            'asset_os'                => ['label' => __('Operating System'), 'weight' => 2],
            'asset_purchase_date'     => ['label' => __('Purchase Date')],
            'asset_warranty_expire'   => ['label' => __('Warranty Expire')],
            'asset_install_date'      => ['label' => __('Install Date')],
            'contact_name'            => ['label' => __('Assigned To')],
            'location_name'           => ['label' => __('Location')],
            'asset_physical_location' => ['label' => __('Physical Location')],
            'asset_notes'             => ['label' => __('Notes'), 'weight' => 3],
            // Available from the handler's join but not exported historically
            'client_name'             => ['label' => __('Client'), 'default' => false],
            'interface_ip'            => ['label' => __('Primary IP'), 'default' => false],
            'interface_mac'           => ['label' => __('Primary MAC'), 'default' => false],
            'asset_status'            => ['label' => __('Status'), 'default' => false],
        ],

        'asset_interfaces' => [
            // The first eight are the import's columns, in its order - VLANs ninth and optional
            'interface_name'        => ['label' => __('Name')],
            'interface_description' => ['label' => __('Description'), 'weight' => 3],
            'interface_type'        => ['label' => __('Type')],
            'interface_mac'         => ['label' => __('MAC')],
            'interface_ip'          => ['label' => __('IP')],
            'interface_nat_ip'      => ['label' => __('NAT IP')],
            'interface_ipv6'        => ['label' => __('IPv6'), 'weight' => 2],
            'network_name'          => ['label' => __('Network')],
            'interface_vlans'       => ['label' => __('VLANs')],
            'network'               => ['label' => __('Subnet'), 'default' => false],
            'connected_to'          => ['label' => __('Connected To'), 'weight' => 2, 'default' => false],
            'interface_notes'       => ['label' => __('Notes'), 'weight' => 3, 'default' => false],
        ],

        'networks' => [
            // The first nine are the import's columns, in its order
            'network_name'          => ['label' => __('Name'), 'weight' => 2],
            'network_description'   => ['label' => __('Description'), 'weight' => 3],
            'network_vlan'          => ['label' => __('VLAN')],
            'network'               => ['label' => __('Network (CIDR)')],
            'network_gateway'       => ['label' => __('Gateway')],
            'network_dhcp_range'    => ['label' => __('IP Range')],
            'network_primary_dns'   => ['label' => __('Primary DNS')],
            'network_secondary_dns' => ['label' => __('Secondary DNS')],
            'network_type'          => ['label' => __('Type')],
            'location_name'         => ['label' => __('Site'), 'default' => false],
            'vendor_name'           => ['label' => __('Provider'), 'default' => false],
            'network_notes'         => ['label' => __('Notes'), 'weight' => 3, 'default' => false],
            'network_tags'          => ['label' => __('Tags'), 'weight' => 2, 'default' => false],
            'client_name'           => ['label' => __('Client'), 'weight' => 2, 'default' => false],
        ],

        'network_ips' => [
            // The import's columns, in its order - Type and MAC after the original three
            'ip_address'     => ['label' => __('IP Address')],
            'ip_hostname'    => ['label' => __('Hostname'), 'weight' => 2],
            'ip_description' => ['label' => __('Description'), 'weight' => 3],
            'ip_type'        => ['label' => __('Type')],
            'ip_mac'         => ['label' => __('MAC')],
            // Available from the handler's join but not exported by default -
            // the export is always scoped to one network of one client
            'network_name'   => ['label' => __('Network'), 'default' => false],
            'client_name'    => ['label' => __('Client'), 'default' => false],
        ],

        'certificates' => [
            'certificate_name'        => ['label' => __('Name'), 'weight' => 2],
            'certificate_description' => ['label' => __('Description'), 'weight' => 3],
            'certificate_domain'      => ['label' => __('Host'), 'weight' => 2],
            'certificate_issued_by'   => ['label' => __('Issuer'), 'weight' => 2],
            'certificate_expire'      => ['label' => __('Expiration Date')],
            // The status column holds a key; the handler aliases its label
            'certificate_check_status' => ['label' => __('Last Check'), 'field' => 'certificate_check_label'],
            'certificate_issued_at'   => ['label' => __('Issued'), 'default' => false],
            'certificate_names'       => ['label' => __('Names Covered'), 'field' => 'certificate_names_list', 'weight' => 3, 'default' => false],
            'certificate_fingerprint' => ['label' => __('SHA-256 Fingerprint'), 'weight' => 3, 'default' => false],
            'certificate_checked_at'  => ['label' => __('Checked At'), 'default' => false],
            'domain_name'             => ['label' => __('Domain'), 'default' => false],
            'certificate_notes'       => ['label' => __('Notes'), 'weight' => 3, 'default' => false],
            'client_name'             => ['label' => __('Client'), 'weight' => 2, 'default' => false],
        ],

        'domains' => [
            'domain_name'        => ['label' => __('Domain'), 'weight' => 2],
            'domain_description' => ['label' => __('Description'), 'weight' => 3],
            // The columns hold vendor IDs; the handler aliases the joined names
            'domain_registrar'   => ['label' => __('Registrar'), 'field' => 'domain_registrar_name'],
            'domain_webhost'     => ['label' => __('Web Host'), 'field' => 'domain_webhost_name'],
            'domain_expire'      => ['label' => __('Expiration Date')],
            'domain_dnshost'     => ['label' => __('DNS Host'), 'field' => 'domain_dnshost_name', 'default' => false],
            'domain_mailhost'    => ['label' => __('Mail Host'), 'field' => 'domain_mailhost_name', 'default' => false],
            'domain_auto_renew'  => ['label' => __('Auto-renew'), 'field' => 'domain_auto_renew_label', 'default' => false],
            'domain_name_servers' => ['label' => __('Name Servers'), 'weight' => 2, 'default' => false],
            'domain_mail_servers' => ['label' => __('MX Records'), 'weight' => 2, 'default' => false],
            'domain_dmarc'       => ['label' => __('DMARC'), 'weight' => 2, 'default' => false],
            'domain_tags'        => ['label' => __('Tags'), 'weight' => 2, 'default' => false],
            'domain_notes'       => ['label' => __('Notes'), 'weight' => 3, 'default' => false],
            'client_name'        => ['label' => __('Client'), 'weight' => 2, 'default' => false],
        ],

        // Defaults match what this export has always emitted, secrets included.
        // The handler decrypts into credential_username / credential_password.
        'credentials' => [
            'credential_name'        => ['label' => __('Name'), 'weight' => 2],
            'credential_description' => ['label' => __('Description'), 'weight' => 3],
            'credential_username'    => ['label' => __('Username')],
            'credential_password'    => ['label' => __('Password')],
            'credential_otp_secret'  => ['label' => __('TOTP')],
            'credential_uri'         => ['label' => __('URI'), 'weight' => 2],
            'credential_uri_2'       => ['label' => __('URI 2'), 'default' => false],
            'credential_tags'        => ['label' => __('Tags'), 'default' => false],
            'asset_name'             => ['label' => __('Asset'), 'default' => false],
            'contact_name'           => ['label' => __('Contact'), 'default' => false],
            'credential_password_changed' => ['label' => __('Password Changed'), 'default' => false],
            'credential_note'        => ['label' => __('Notes'), 'weight' => 3, 'default' => false],
            'client_name'            => ['label' => __('Client'), 'default' => false],
        ],

        // seats_used, assigned_to_assets / assigned_to_contacts, software_keys_list and software_tags are built by the handler's query
        'software' => [
            'software_name'         => ['label' => __('Name'), 'weight' => 2],
            'software_version'      => ['label' => __('Version')],
            'software_description'  => ['label' => __('Description'), 'weight' => 3],
            'software_type'         => ['label' => __('Type')],
            'software_license_type' => ['label' => __('License Type')],
            'software_seats'        => ['label' => __('Seats'), 'format' => 'number'],
            'seats_used'            => ['label' => __('Seats Used'), 'format' => 'number'],
            'software_key'          => ['label' => __('Key'), 'weight' => 2],
            'software_keys_list'    => ['label' => __('Individual Keys'), 'weight' => 3, 'default' => false],
            'assigned_to_assets'    => ['label' => __('Assets'), 'weight' => 2],
            'assigned_to_contacts'  => ['label' => __('Contacts'), 'weight' => 2],
            'vendor_name'           => ['label' => __('Vendor'), 'default' => false],
            'software_purchase_reference' => ['label' => __('Order Ref'), 'default' => false],
            'software_purchase'     => ['label' => __('Purchased')],
            'software_expire'       => ['label' => __('Expires')],
            'client_name'           => ['label' => __('Client'), 'default' => false],
            'software_tags'         => ['label' => __('Tags'), 'weight' => 2, 'default' => false],
            'software_notes'        => ['label' => __('Notes'), 'weight' => 3],
        ],

        // ticket_number_display is prefixed by the handler
        'tickets' => [
            'ticket_number_display' => ['label' => __('Ticket Number')],
            'ticket_priority'       => ['label' => __('Priority')],
            'ticket_status_name'    => ['label' => __('Status')],
            'ticket_subject'        => ['label' => __('Subject'), 'weight' => 3],
            'ticket_created_at'     => ['label' => __('Date Opened')],
            'ticket_resolved_at'    => ['label' => __('Date Resolved')],
            'ticket_closed_at'      => ['label' => __('Date Closed')],
            'client_name'           => ['label' => __('Client'), 'default' => false],
            'contact_name'          => ['label' => __('Contact'), 'default' => false],
            'ticket_assigned_to'    => ['label' => __('Assigned To'), 'default' => false],
            'ticket_category_name'  => ['label' => __('Category'), 'default' => false],
            'ticket_billable'       => ['label' => __('Billable'), 'default' => false],
            'ticket_tags'           => ['label' => __('Tags'), 'weight' => 2, 'default' => false],
        ],

        // Projects - the handler adds the state, progress and hours (projectStats())
        'projects' => [
            'project_reference'   => ['label' => __('Number')],
            'project_name'        => ['label' => __('Project'), 'weight' => 3],
            'client_name'         => ['label' => __('Client'), 'weight' => 2],
            'project_state'       => ['label' => __('State')],
            'user_name'           => ['label' => __('Manager')],
            'contact_name'        => ['label' => __('Client Contact'), 'default' => false],
            'project_start'       => ['label' => __('Start'), 'default' => false],
            'project_due'         => ['label' => __('Due')],
            'project_completed'   => ['label' => __('Completed')],
            'tickets_done'        => ['label' => __('Tickets Done'), 'format' => 'number'],
            'tickets_total'       => ['label' => __('Tickets'), 'format' => 'number'],
            'tasks_done'          => ['label' => __('Tasks Done'), 'format' => 'number', 'default' => false],
            'tasks_total'         => ['label' => __('Tasks'), 'format' => 'number', 'default' => false],
            'hours_logged'        => ['label' => __('Hours Logged'), 'format' => 'number'],
            'project_budget_hours' => ['label' => __('Budget Hours'), 'format' => 'number'],
            'hours_unbilled'      => ['label' => __('Hours Not Invoiced'), 'format' => 'number', 'default' => false],
            'project_description' => ['label' => __('Scope'), 'weight' => 3, 'default' => false],
        ],

        // Services - the counts, attention and review columns are built by the handler
        'services' => [
            'service_name'        => ['label' => __('Service'), 'weight' => 2],
            'client_name'         => ['label' => __('Client'), 'weight' => 2],
            'service_category'    => ['label' => __('Category')],
            'service_importance'  => ['label' => __('Importance')],
            'service_description' => ['label' => __('Description'), 'weight' => 3],
            'service_assets'      => ['label' => __('Runs On'), 'weight' => 3],
            'service_vendors'     => ['label' => __('Vendors'), 'weight' => 2],
            'service_contacts'    => ['label' => __('People'), 'weight' => 2, 'default' => false],
            'service_domains'     => ['label' => __('Domains'), 'weight' => 2, 'default' => false],
            'service_backup'      => ['label' => __('Backup and Recovery'), 'weight' => 3],
            'service_attention'   => ['label' => __('Needs Attention'), 'weight' => 3],
            'service_review'      => ['label' => __('Next Review')],
            'service_reviewed'    => ['label' => __('Last Reviewed'), 'default' => false],
            'service_review_every' => ['label' => __('Review Every'), 'default' => false],
            'open_tickets'        => ['label' => __('Open Tickets'), 'format' => 'number', 'default' => false],
            'service_state'       => ['label' => __('State'), 'default' => false],
            'service_tags'        => ['label' => __('Tags'), 'weight' => 2, 'default' => false],
        ],

        // invoice_number_display is prefix . number, built by the handler
        'invoices' => [
            'invoice_number_display' => ['label' => __('Invoice Number')],
            'invoice_scope'          => ['label' => __('Scope'), 'weight' => 3],
            'invoice_subtotal'       => ['label' => __('Subtotal'), 'format' => 'money', 'default' => false],
            'invoice_discount_amount' => ['label' => __('Discount'), 'format' => 'money', 'default' => false],
            'invoice_tax'            => ['label' => __('Tax'), 'format' => 'money', 'default' => false],
            'invoice_tax_parts'      => ['label' => __('Tax by Part'), 'weight' => 2, 'default' => false],
            'invoice_amount'         => ['label' => __('Amount'), 'format' => 'money'],
            'invoice_date'           => ['label' => __('Issued Date')],
            'invoice_due'            => ['label' => __('Due Date')],
            'invoice_status'         => ['label' => __('Status')],
            'client_name'            => ['label' => __('Client'), 'weight' => 2],
            'category_name'          => ['label' => __('Category'), 'default' => false],
            'invoice_currency_code'  => ['label' => __('Currency'), 'default' => false],
            'amount_paid'            => ['label' => __('Paid'), 'format' => 'money', 'default' => false],
            'invoice_balance'        => ['label' => __('Balance'), 'format' => 'money', 'default' => false],
        ],

        'quotes' => [
            'quote_number_display' => ['label' => __('Quote Number')],
            'quote_scope'          => ['label' => __('Scope'), 'weight' => 3],
            'quote_amount'         => ['label' => __('Amount'), 'format' => 'money'],
            'quote_tax'            => ['label' => __('Tax'), 'format' => 'money', 'default' => false],
            'quote_monthly_amount' => ['label' => __('Monthly'), 'format' => 'money'],
            'quote_date'           => ['label' => __('Date')],
            'quote_status'         => ['label' => __('Status')],
            'client_name'          => ['label' => __('Client'), 'weight' => 2, 'default' => false],
            'category_name'        => ['label' => __('Category'), 'default' => false],
        ],

        'recurring_invoices' => [
            'recurring_invoice_number_display' => ['label' => __('Recurring Number')],
            'recurring_invoice_scope'          => ['label' => __('Scope'), 'weight' => 3],
            'recurring_invoice_amount'         => ['label' => __('Amount'), 'format' => 'money'],
            'recurring_invoice_tax'            => ['label' => __('Tax'), 'format' => 'money', 'default' => false],
            'recurring_invoice_frequency_display' => ['label' => __('Frequency')],
            'recurring_invoice_created_at'     => ['label' => __('Date Created')],
            'client_name'                      => ['label' => __('Client'), 'weight' => 2, 'default' => false],
            'category_name'                    => ['label' => __('Category'), 'default' => false],
        ],

        'products' => [
            'product_name'          => ['label' => __('Product'), 'weight' => 2],
            'product_type_label'    => ['label' => __('Type'), 'default' => false],
            'product_code'          => ['label' => __('Code')],
            'product_description'   => ['label' => __('Description'), 'weight' => 3],
            'product_price'         => ['label' => __('Price'), 'format' => 'money'],
            'product_cost'          => ['label' => __('Cost'), 'format' => 'money', 'default' => false],
            'product_margin'        => ['label' => __('Margin'), 'default' => false],
            'product_billed'        => ['label' => __('Billed')],
            'product_currency_code' => ['label' => __('Currency'), 'default' => false],
            'category_name'         => ['label' => __('Category')],
            'product_tax'           => ['label' => __('Tax')],
            'vendor_name'           => ['label' => __('Vendor'), 'default' => false],
            'product_vendor_part'   => ['label' => __('Part Number'), 'default' => false],
            'product_stock_display' => ['label' => __('In Stock'), 'default' => false],
            'product_stock_value'   => ['label' => __('Stock Value'), 'format' => 'money', 'default' => false],
            'product_reorder_display' => ['label' => __('Reorder At'), 'default' => false],
            'product_order_display' => ['label' => __('Order'), 'default' => false],
            'product_location'      => ['label' => __('Location'), 'default' => false],
            'product_asset_type'    => ['label' => __('Asset Type'), 'default' => false],
            'product_make'          => ['label' => __('Make'), 'default' => false],
            'product_model'         => ['label' => __('Model'), 'default' => false],
            'product_warranty_display' => ['label' => __('Warranty'), 'default' => false],
            'product_notes'         => ['label' => __('Notes'), 'weight' => 3, 'default' => false],
        ],

        'expenses' => [
            'expense_date'        => ['label' => __('Date')],
            'expense_amount'      => ['label' => __('Amount'), 'format' => 'money'],
            'expense_before_tax'  => ['label' => __('Before Tax'), 'format' => 'money', 'default' => false],
            'expense_tax_amount'  => ['label' => __('Tax'), 'format' => 'money', 'default' => false],
            'expense_tax_rate'    => ['label' => __('Tax Rate'), 'default' => false],
            'vendor_name'         => ['label' => __('Vendor'), 'weight' => 2],
            'expense_description' => ['label' => __('Description'), 'weight' => 3],
            'category_name'       => ['label' => __('Category')],
            'account_name'        => ['label' => __('Account')],
            'client_name'         => ['label' => __('Client'), 'weight' => 2, 'default' => false],
            'expense_payment_method' => ['label' => __('Paid By'), 'default' => false],
            'expense_reference'   => ['label' => __('Reference'), 'default' => false],
            'expense_receipt_display' => ['label' => __('Receipt'), 'default' => false],
            'expense_paid_by_display' => ['label' => __('Paid Personally'), 'default' => false],
            'expense_billing_display' => ['label' => __('Billing'), 'default' => false],
            'expense_billed_on'   => ['label' => __('Billed On'), 'default' => false],
            'user_name'           => ['label' => __('Entered By'), 'default' => false],
        ],

        'income' => [
            'income_date'          => ['label' => __('Date')],
            'income_type'          => ['label' => __('Type')],
            'income_invoice'       => ['label' => __('Invoice')],
            'income_source'        => ['label' => __('What for'), 'weight' => 3],
            'income_category'      => ['label' => __('Category')],
            'income_client'        => ['label' => __('Client'), 'weight' => 2],
            'income_amount'        => ['label' => __('Amount'), 'format' => 'money'],
            'income_currency_code' => ['label' => __('Currency')],
            'income_method'        => ['label' => __('Payment Method')],
            'income_reference'     => ['label' => __('Reference')],
            'income_account'       => ['label' => __('Account')],
            'income_online'        => ['label' => __('Paid Online')],
        ],

        'transactions' => [
            'transaction_date'           => ['label' => __('Date')],
            'transaction_account'        => ['label' => __('Account')],
            'transaction_type'           => ['label' => __('Type')],
            'transaction_what'           => ['label' => __('Who or What'), 'weight' => 2],
            'transaction_description'    => ['label' => __('Description'), 'weight' => 3],
            'transaction_other_account'  => ['label' => __('Transfer Account')],
            'transaction_reference'      => ['label' => __('Reference')],
            'transaction_category'       => ['label' => __('Category')],
            'transaction_client'         => ['label' => __('Client'), 'weight' => 2],
            'transaction_payment_method' => ['label' => __('Payment Method')],
            'transaction_in'             => ['label' => __('In'), 'format' => 'money'],
            'transaction_out'            => ['label' => __('Out'), 'format' => 'money'],
            'transaction_amount'         => ['label' => __('Amount'), 'format' => 'money', 'default' => false],
            'transaction_balance'        => ['label' => __('Balance'), 'format' => 'money'],
            'transaction_cleared'        => ['label' => __('Cleared'), 'default' => false],
        ],

        'trips' => [
            'trip_date'        => ['label' => __('Date')],
            'user_name'        => ['label' => __('Driver'), 'weight' => 2],
            'trip_purpose'     => ['label' => __('Purpose'), 'weight' => 3],
            'trip_source'      => ['label' => __('From'), 'weight' => 2],
            'trip_destination' => ['label' => __('To'), 'weight' => 2],
            'trip_miles'       => ['label' => ucfirst(tripUnit()), 'format' => 'number'],
            'round_trip_display' => ['label' => __('Round Trip'), 'default' => false],
            'trip_one_way'     => ['label' => __('One Way'), 'format' => 'number', 'default' => false],
            'client_name'      => ['label' => __('Client'), 'weight' => 2, 'default' => false],
            'location_name'    => ['label' => __('Site'), 'weight' => 2, 'default' => false],
            'ticket_display'   => ['label' => __('Ticket'), 'weight' => 2, 'default' => false],
            'trip_start_odometer' => ['label' => __('Odometer Start'), 'default' => false],
            'trip_end_odometer' => ['label' => __('Odometer End'), 'default' => false],
            'vehicle_display'  => ['label' => __('Vehicle'), 'default' => false],
            'paid_back_display' => ['label' => __('Paid Back'), 'weight' => 2, 'default' => false],
            'billing_display'  => ['label' => __('Billing'), 'weight' => 2, 'default' => false],
        ],

        // user_status_display is the status word, built by the handler
        'users' => [
            'user_name'          => ['label' => __('Name'), 'weight' => 2],
            'user_email'         => ['label' => __('Email'), 'weight' => 2],
            'role_name'          => ['label' => __('Role')],
            'user_status_display' => ['label' => __('Status')],
            'user_mfa_display'   => ['label' => __('MFA')],
            'user_last_login'    => ['label' => __('Last Sign In')],
            'user_client_access' => ['label' => __('Client Access'), 'weight' => 2, 'default' => false],
            'user_created_at'    => ['label' => __('Creation Date')],
        ],

        // Audit log entries - handler adds who did it, the record and the device (functions/audit_log.php)
        'audit_logs' => [
            'log_created_at'     => ['label' => __('When')],
            'audit_who'          => ['label' => __('Who')],
            'user_email'         => ['label' => __('Email'), 'default' => false],
            'client_name'        => ['label' => __('Client')],
            'log_type'           => ['label' => __('Type')],
            'log_action'         => ['label' => __('Action')],
            'log_description'    => ['label' => __('Description'), 'weight' => 3],
            'audit_record'       => ['label' => __('Record'), 'weight' => 2, 'default' => false],
            'log_ip'             => ['label' => __('IP Address')],
            'audit_device'       => ['label' => __('Device')],
            'log_user_agent'     => ['label' => __('User Agent'), 'weight' => 2, 'default' => false],
            'log_id'             => ['label' => __('Entry'), 'default' => false],
        ],

        'app_logs' => [
            'app_log_created_at' => ['label' => __('When')],
            'app_log_type'       => ['label' => __('Level')],
            'app_log_category'   => ['label' => __('Category')],
            'app_log_details'    => ['label' => __('Details'), 'weight' => 4],
            'app_log_id'         => ['label' => __('Entry'), 'default' => false],
        ],

    ];

    // An asset's details, a column each - off until picked (ASSET_SPECS, functions/asset_type.php)
    if ($export_type === 'assets' && defined('ASSET_SPECS')) {
        foreach (ASSET_SPECS as $spec_key => $spec) {
            $registry['assets']['spec_' . $spec_key] = ['label' => (function_exists('assetSpecLabel') ? assetSpecLabel($spec_key) : $spec['label']) . (isset($spec['unit']) ? ' (' . $spec['unit'] . ')' : ''), 'default' => false];
        }
    }

    return $registry[$export_type] ?? [];
}

/*
 * Which columns the modal ticked, whitelisted against the registry.
 * Registry order always wins, so the file layout is stable no matter what order
 * the checkboxes came back in. Nothing ticked (or no picker on the form) falls
 * back to the export's defaults.
 */
function resolveExportColumns($export_type) {

    $available = getExportColumns($export_type);
    if (empty($available)) {
        return [];
    }

    $requested = $_POST['columns'] ?? [];
    if (!is_array($requested)) {
        $requested = [];
    }

    $selected = [];
    foreach ($available as $key => $column) {
        if (in_array($key, $requested, true)) {
            $selected[$key] = $column;
        }
    }

    if (empty($selected)) {
        foreach ($available as $key => $column) {
            if ($column['default'] ?? true) {
                $selected[$key] = $column;
            }
        }
    }

    return $selected;
}

/*
 * The export buttons post their format as the value of the trigger, so
 * $_POST['export_assets'] is the string 'csv' or 'pdf'. Anything else is CSV.
 */
function resolveExportFormat($format) {
    return ($format === 'pdf') ? 'pdf' : 'csv';
}

/*
 * The gate every export handler opens on. Keying on isset() alone means any other
 * field that happens to share the trigger's name fires the export - the client PDF
 * pack's section checkboxes (export_assets=1, export_contacts=1, ...) did exactly
 * that, and since post.php loads every handler, the first match won and streamed a
 * CSV instead. Only 'csv' or 'pdf' - what renderExportButtons() posts - counts.
 */
function isExportRequest($trigger) {
    return isset($_POST[$trigger]) && in_array($_POST[$trigger], ['csv', 'pdf'], true);
}

/*
 * Filters that match nothing say so and go back to the list - the export form
 * posts straight to post.php, so returning nothing left a blank page there.
 * Call it once the row count is known, before beginExport().
 */
function guardExportHasRows($num_rows, $what) {
    if ($num_rows < 1) {
        flashAlert(__('No %s match - nothing to export.', $what), 'error');
        redirect();
    }
}

/*
 * PDF is capped - see EXPORT_PDF_MAX_ROWS. Call this after the row count is known
 * and before beginExport(); it redirects rather than returning on refusal.
 */
function guardExportPdfRowCount($format, $num_rows) {
    if (resolveExportFormat($format) === 'pdf' && $num_rows > EXPORT_PDF_MAX_ROWS) {
        flashAlert(__('That\'s %s rows - too many for a PDF. Narrow the filters or export to CSV instead.', number_format($num_rows)), 'error');
        redirect();
    }
}

/*
 * Presentation only. CSV keeps numbers raw so spreadsheets and importers still
 * see a number; the PDF is for reading, so it gets thousands separators.
 */
function formatExportValue($value, $format, $output, $row = [], $field = '') {

    // An empty field is empty, whatever its format - a blank amount is not 0.00
    if ($value === null || $value === '') {
        return '';
    }

    if ($format === 'phone') {
        // Every phone column in the schema is paired with <column>_country_code,
        // and the export queries all select the table wholesale, so the code is
        // already in the row - it just was not reachable from here before.
        return formatPhoneNumber($value, $row[$field . '_country_code'] ?? '');
    }

    if ($output === 'pdf' && $format === 'money') {
        return number_format(floatval($value), 2);
    }

    if ($output === 'pdf' && $format === 'number') {
        return rtrim(rtrim(number_format(floatval($value), 2), '0'), '.');
    }

    return $value;
}

/*
 * Renders the column picker into an export modal. Drop it in the modal body:
 *
 *     <?php renderExportColumnPicker('assets'); ?>
 *
 * Posts back as columns[]. Silently renders nothing for an export type that has
 * no registry entry yet, so a half-converted modal still works.
 */
function renderExportColumnPicker($export_type) {

    $available = getExportColumns($export_type);
    if (empty($available)) {
        return;
    }

    // Modals get appended to the DOM, so the container id has to be unique per instance
    static $instance = 0;
    $instance++;
    $picker_id = 'exportColumns' . $instance;

    ?>

    <div class="mb-3">
        <label class="d-flex justify-content-between align-items-center">
            <span><?= __('Columns') ?></span>
            <span>
                <button type="button" class="btn btn-link btn-sm p-0 me-2 export-columns-all"><?= __('Select all') ?></button>
                <button type="button" class="btn btn-link btn-sm p-0 export-columns-none"><?= __('None') ?></button>
            </span>
        </label>
        <div id="<?= $picker_id ?>" class="export-column-picker border rounded p-2" style="max-height: 220px; overflow-y: auto;">
            <div class="row">
                <?php foreach ($available as $column_key => $column) { ?>
                    <div class="col-md-6">
                        <label class="d-block mb-1 fw-normal text-truncate" title="<?= escapeHtml($column['label']) ?>">
                            <input class="form-check-input" type="checkbox" name="columns[]" value="<?= $column_key ?>" <?php if ($column['default'] ?? true) { echo 'checked'; } ?>>
                            <?= escapeHtml($column['label']) ?>
                        </label>
                    </div>
                <?php } ?>
            </div>
        </div>
        <small class="form-text text-muted export-columns-count"></small>
    </div>

    <script>
    (function () {
        var picker = document.getElementById('<?= $picker_id ?>');
        if (!picker) {
            return;
        }

        // Everything is looked up relative to the picker's own form group, so several
        // pickers on one page - or several modals in a session - never cross wires
        var group = picker.parentNode;
        var boxes = picker.querySelectorAll('input[name="columns[]"]');
        var counter = group.querySelector('.export-columns-count');
        var form = picker.closest('form');

        var text = <?= json_encode(['selected' => __('%1$s of %2$s columns selected'), 'many' => __('%1$s of %2$s columns selected - a lot for a PDF, CSV will read better'), 'none' => __('Select at least one column to export')], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        function fill(pattern, a, b) {
            return pattern.replace('%1$s', a).replace('%2$s', b);
        }

        function setAll(state) {
            for (var i = 0; i < boxes.length; i++) {
                boxes[i].checked = state;
            }
            update();
        }

        function update() {
            var checked = picker.querySelectorAll('input[name="columns[]"]:checked').length;
            counter.classList.remove('text-danger');
            counter.textContent = fill(checked > 10 ? text.many : text.selected, checked, boxes.length);
        }

        picker.addEventListener('change', update);
        group.querySelector('.export-columns-all').addEventListener('click', function () { setAll(true); });
        group.querySelector('.export-columns-none').addEventListener('click', function () { setAll(false); });

        // An empty selection would silently fall back to the defaults server side -
        // stop it here so the choice stays the user's
        if (form) {
            form.addEventListener('submit', function (e) {
                if (picker.querySelectorAll('input[name="columns[]"]:checked').length === 0) {
                    e.preventDefault();
                    counter.textContent = text.none;
                    counter.classList.add('text-danger');
                }
            });
        }

        update();
    })();
    </script>

    <?php
}

/*
 * Builds the export modal's data-modal-url with the page's current filters attached,
 * so the list page doesn't need a hand-rolled query string per export button:
 *
 *     data-modal-url="<?= buildExportModalUrl('modals/asset/asset_export.php', ['client_id', 'type', 'q']) ?>"
 *
 * Empty params are dropped and array params (tags[], status[]) survive. The return
 * value is escaped for use in an HTML attribute.
 */
function buildExportModalUrl($modal_path, $names, $extra = []) {

    $params = [];
    foreach ($names as $name) {
        if (!isset($_GET[$name]) || $_GET[$name] === '' || $_GET[$name] === []) {
            continue;
        }
        $params[$name] = $_GET[$name];
    }

    // Filters the page derived rather than read straight off the query string -
    // filter_header.php's canned date ranges being the main one
    foreach ($extra as $name => $value) {
        if ($value !== '' && $value !== null && $value !== []) {
            $params[$name] = $value;
        }
    }

    if (empty($params)) {
        return escapeHtml($modal_path);
    }

    return escapeHtml($modal_path . '?' . http_build_query($params));
}

/*
 * filter_header.php falls back to 1970-01-01 / 2099-12-31 when no date range is
 * chosen, so a naive summary would claim a filter that isn't really there.
 * Returns '' for the all-time case, otherwise a readable range.
 */
function formatExportDateRange($date_from, $date_to) {

    $all_time_from = ($date_from === '' || $date_from === null || $date_from === '1970-01-01' || $date_from === '0000-00-00');
    $all_time_to = ($date_to === '' || $date_to === null || $date_to === '2099-12-31' || $date_to === '9999-00-00');

    if ($all_time_from && $all_time_to) {
        return '';
    }

    if ($all_time_from) {
        return __('Up to %s', formatDate($date_to, 'date'));
    }
    if ($all_time_to) {
        return __('From %s', formatDate($date_from, 'date'));
    }
    return __('%1$s to %2$s', formatDate($date_from, 'date'), formatDate($date_to, 'date'));
}

/*
 * The name of a client an export is filtered to, for its summary - only a
 * client this agent can reach; '' otherwise, so a made-up filter can't be
 * used to read another client's name (the rows are scoped all the same).
 */
function exportClientName($client_id) {
    $client_id = intval($client_id);
    return $client_id && hasClientAccess($client_id) ? (string) getFieldById('clients', $client_id, 'client_name') : '';
}

/**
 * The same for a record an export is filtered to (a site, a project, a vendor) - its $name_column
 * when it belongs to no client (the company's own, which every agent sees) or to one this agent can reach.
 */
function exportRecordName($table, $id, $name_column, $client_column) {
    $id = intval($id);
    if (!$id) {
        return '';
    }
    $client_id = getFieldById($table, $id, $client_column);
    if ($client_id === null || $client_id === false || $client_id === '' || (intval($client_id) && !hasClientAccess($client_id))) {
        return '';
    }
    return (string) getFieldById($table, $id, $name_column);
}

/*
 * Human-readable one-liner of the same thing, for the PDF subtitle.
 */
function summarizeExportFilters($filters) {

    $parts = [];
    foreach ($filters as $label => $value) {
        if ($value !== '' && $value !== null) {
            $parts[] = $label . ': ' . $value;
        }
    }

    return empty($parts) ? '' : __('Filters - %s', implode(' | ', $parts));
}

/*
 * The tabbed shell every export modal uses - Filters on the first pane, Columns on the
 * second, same pill nav as the add-client modal. Ids are per-instance because modals are
 * appended to the DOM rather than living in the page.
 *
 * Sits between the modal header and the form:
 *
 *     <?php exportTabsNav(); ?>
 *     <form ...>
 *         <div class="modal-body">
 *             <?php exportTabsFiltersOpen(); ?>
 *                 ...filter controls...
 *             <?php exportTabsColumns('assets'); ?>
 *         </div>
 *
 * exportTabsColumns() closes the filters pane, renders the picker in the second pane and
 * closes the tab content, so the modal body needs nothing else.
 */
function exportTabsId() {
    static $instance = 0;
    static $current = '';
    if (func_num_args() > 0 && func_get_arg(0) === 'next') {
        $instance++;
        $current = 'exportTab' . $instance;
    }
    return $current;
}

function exportTabsNav($filters_label = '') {
    $id = exportTabsId('next');
    $filters_label = $filters_label !== '' ? $filters_label : __('Filters');
    ?>
    <ul class="modal-header nav nav-pills nav-justified">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="pill" href="#<?= $id ?>-filters"><?= escapeHtml($filters_label) ?></a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="pill" href="#<?= $id ?>-columns"><?= __('Columns') ?></a>
        </li>
    </ul>
    <?php
}

function exportTabsFiltersOpen() {
    $id = exportTabsId();
    ?>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="<?= $id ?>-filters">
    <?php
}

function exportTabsColumns($export_type) {
    $id = exportTabsId();
    ?>
        </div>
        <div class="tab-pane fade" id="<?= $id ?>-columns">
            <?php renderExportColumnPicker($export_type); ?>
        </div>
    </div>
    <?php
}

/*
 * Renders the CSV / PDF submit pair for an export modal footer.
 * $trigger is the POST key the handler keys on, e.g. 'export_assets'.
 */
function renderExportButtons($trigger) {
    ?>
    <button type="submit" name="<?= $trigger ?>" value="csv" class="btn btn-primary text-bold"><i class="fas fa-fw fa-file-csv me-2"></i><?= __('Download CSV') ?></button>
    <button type="submit" name="<?= $trigger ?>" value="pdf" class="btn btn-secondary text-bold"><i class="fas fa-fw fa-file-pdf me-2"></i><?= __('Download PDF') ?></button>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i><?= __('Cancel') ?></button>
    <?php
}

/*
 * Opens an export. $filename_base is everything before the timestamp, e.g.
 * "Acme Inc-Assets"; the date and extension are appended here.
 * $subtitle is the human summary of the filters in play - it prints under the
 * title in the PDF and is ignored for CSV.
 */
function beginExport($export_type, $format, $filename_base, $title = '', $subtitle = '') {

    $format = resolveExportFormat($format);
    $columns = resolveExportColumns($export_type);

    $export = [
        'format'   => $format,
        'columns'  => $columns,
        'title'    => $title,
        'subtitle' => $subtitle,
        'filename' => sanitizeFilename($filename_base . '-' . date('Y-m-d_H-i-s') . '.' . $format),
        'rows'     => 0,
        'fp'       => null,
        'body'     => '',
        'widths'   => [],
        'missing'  => [],
    ];

    if ($format === 'csv') {
        $export['fp'] = fopen('php://memory', 'w');
        // Headings stay English in every language - an export opens straight back in by them
        fputcsv($export['fp'], i18nEnglish(fn () => array_column(resolveExportColumns($export_type), 'label')), ',', '"', '\\');
    } else {
        // TCPDF sizes a table from the cells it sees, so every row needs the widths -
        // putting them on the header alone leaves the body offset from its headings
        $total_weight = 0;
        foreach ($columns as $column) {
            $total_weight += $column['weight'] ?? 1;
        }
        foreach ($columns as $column_key => $column) {
            $export['widths'][$column_key] = round((($column['weight'] ?? 1) / $total_weight) * 100, 2);
        }
    }

    return $export;
}

/*
 * One row. $row is a plain associative array - normally straight out of
 * mysqli_fetch_assoc(), with any handler-computed keys already set on it.
 */
function addExportRow(&$export, $row) {

    $export['rows']++;

    $values = [];
    foreach ($export['columns'] as $column_key => $column) {
        $field = $column['field'] ?? $column_key;

        // A selected column whose field the query never returned would otherwise be a
        // silently blank column all the way down - note it so finishExport can shout
        if (!array_key_exists($field, $row)) {
            $export['missing'][$field] = true;
        }

        $values[$column_key] = formatExportValue($row[$field] ?? '', $column['format'] ?? '', $export['format'], $row, $field);
    }

    if ($export['format'] === 'csv') {
        fputcsv($export['fp'], array_map('escapeCsvFormula', $values), ',', '"', '\\');
        return;
    }

    // PDF - buffer the row, the document is assembled in finishExport()
    $stripe = ($export['rows'] % 2 === 0) ? ' bgcolor="#f4f4f4"' : '';
    $cells = '';
    foreach ($export['columns'] as $column_key => $column) {
        $align = in_array($column['format'] ?? '', ['money', 'number'], true) ? 'right' : 'left';
        $width = $export['widths'][$column_key] ?? 0;

        // Blank cells read as a mistake in a printed table - an explicit dash doesn't
        $cell = ($values[$column_key] === '') ? '-' : escapeHtml($values[$column_key]);

        $cells .= '<td width="' . $width . '%" align="' . $align . '">' . $cell . '</td>';
    }
    $export['body'] .= '<tr' . $stripe . '>' . $cells . '</tr>';
}

/*
 * A column whose field the query never selected produces a blank column rather than an
 * error, which is exactly the kind of thing that ships unnoticed. Nothing is shown to
 * the user - the file is still valid - but it lands in the PHP error log where a
 * conversion mistake will be spotted.
 */
function reportMissingExportFields(&$export) {
    if (!empty($export['missing'])) {
        error_log('ITFlow export: no such field in result set for column(s): ' . implode(', ', array_keys($export['missing'])));
    }
}

/*
 * Streams the finished file. Sends its own headers, so nothing may be echoed
 * before it; the caller still owns the logAudit() call and the exit.
 */
function finishExport(&$export) {

    global $session_company_name;

    reportMissingExportFields($export);

    if ($export['format'] === 'csv') {
        fseek($export['fp'], 0);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $export['filename'] . '";');
        fpassthru($export['fp']);
        fclose($export['fp']);
        return $export['rows'];
    }

    require_once __DIR__ . '/../libs/TCPDF/tcpdf.php';

    $column_count = count($export['columns']);

    // Anything past a handful of columns needs the long edge
    $orientation = $column_count > 5 ? 'L' : 'P';

    // Shrink type as columns pile up rather than letting TCPDF wrap every cell
    if ($column_count <= 6) {
        $font_size = 8;
    } elseif ($column_count <= 9) {
        $font_size = 7;
    } elseif ($column_count <= 12) {
        $font_size = 6;
    } else {
        $font_size = 5;
    }

    $pdf = new TCPDF($orientation, 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($session_company_name);
    $pdf->SetTitle($export['title']);
    $pdf->SetPrintHeader(false);
    $pdf->SetMargins(8, 8, 8);
    $pdf->SetAutoPageBreak(true, 12);
    $pdf->AddPage();
    $pdf->SetFont('freeserif', '', $font_size);

    // Same widths the body rows used - see beginExport()
    $head = '';
    foreach ($export['columns'] as $column_key => $column) {
        $width = $export['widths'][$column_key] ?? 0;
        $head .= '<th width="' . $width . '%">' . escapeHtml($column['label']) . '</th>';
    }

    $html = '
    <style>
        h1 { font-size: ' . ($font_size + 6) . 'pt; margin: 0; }
        p.meta { font-size: ' . $font_size . 'pt; color: #555555; margin: 2px 0 0 0; }
        table { width: 100%; border-collapse: collapse; }
        table, th, td { border: 0.5px solid #999999; }
        th { background-color: #343a40; color: #ffffff; text-align: left; font-weight: bold; padding: 3px; }
        td { padding: 3px; }
        thead { display: table-header-group; }
    </style>
    <h1>' . escapeHtml($export['title']) . '</h1>
    <p class="meta">' . escapeHtml($session_company_name) . '</p>';

    if (!empty($export['subtitle'])) {
        $html .= '<p class="meta">' . escapeHtml($export['subtitle']) . '</p>';
    }

    $html .= '<p class="meta">' . escapeHtml(_n('%1$s record - generated %2$s', '%1$s records - generated %2$s', $export['rows'], number_format($export['rows']), formatDate(date('Y-m-d H:i:s'), 'datetime'))) . '</p><br>
    <table cellspacing="0" cellpadding="2">
        <thead><tr>' . $head . '</tr></thead>
        <tbody>' . $export['body'] . '</tbody>
    </table>';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output($export['filename'], 'D');

    return $export['rows'];
}
