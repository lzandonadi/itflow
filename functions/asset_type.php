<?php

/*
 * Asset types - every kind of asset ITFlow knows, what each is drawn with and
 * what is asked about it.
 *
 * ASSET_TYPES is the list the pickers offer, in their order. Each type has its
 * icon, the colour family it is drawn in (itflow-asset-tone-*), the group the
 * assets list, the client overview and the portal put it in, the standard
 * fields that make sense for it, and its details: the facts only that kind of
 * thing has - a switch's ports, a UPS's battery, a VM's host - from
 * ASSET_SPECS, kept in asset_specs one row a detail. A type a hand-made
 * request or the API wrote that isn't here is drawn as Other.
 *
 * Fields (ASSET_FIELDS) a type doesn't list are hidden on its form, not
 * dropped: a value already there is still shown, and still saved, so an
 * asset whose type changes loses nothing it had. Details a type doesn't have
 * are dropped when it is saved as that type.
 *
 * Ports: a type with ports or sfp_ports has the interfaces made for it when
 * it is saved with a new count - as many as are missing, never taken away
 * (interfacePortsEnsure() in functions/asset_interface.php).
 */

/** The standard fields a type can show or hide - Name, type, status, description, tags, make, model, location and notes are on every one. */
const ASSET_FIELDS = ['contact', 'os', 'network', 'nat', 'links', 'serial', 'purchase', 'warranty'];

/**
 * The asset types, in the pickers' order - name => icon, tone, group, fields
 * (from ASSET_FIELDS), os (what the OS field is called on it: 'os' or
 * 'firmware'), specs (keys of ASSET_SPECS) and example (what the form's
 * name, description, make, model and OS fields suggest for it).
 */
// i18n-ignore-start - labels and examples are shown through assetTypeLabel(), assetSpecLabel(), assetTypeExample() and the like
const ASSET_TYPES = [
    'Laptop' => ['icon' => 'fa-laptop', 'tone' => 'workstation', 'group' => 'workstation', 'os' => 'os',
        'fields' => ['contact', 'os', 'network', 'links', 'serial', 'purchase', 'warranty'], 'specs' => ['cpu', 'ram_gb', 'storage'],
        'example' => ['name' => 'RDG-LT-04', 'description' => 'Front desk laptop', 'make' => 'Dell', 'model' => 'Latitude 5450', 'os' => 'Windows 11 Pro 24H2']],
    'Desktop' => ['icon' => 'fa-desktop', 'tone' => 'workstation', 'group' => 'workstation', 'os' => 'os',
        'fields' => ['contact', 'os', 'network', 'links', 'serial', 'purchase', 'warranty'], 'specs' => ['cpu', 'ram_gb', 'storage'],
        'example' => ['name' => 'RDG-WS-12', 'description' => 'Reception PC', 'make' => 'HP', 'model' => 'EliteDesk 800 G9', 'os' => 'Windows 11 Pro 24H2']],
    'Server' => ['icon' => 'fa-server', 'tone' => 'server', 'group' => 'server', 'os' => 'os',
        'fields' => ['os', 'network', 'nat', 'links', 'serial', 'purchase', 'warranty'], 'specs' => ['cpu', 'ram_gb', 'storage'],
        'example' => ['name' => 'SRV-DC01', 'description' => 'Domain controller for HQ', 'make' => 'Dell', 'model' => 'PowerEdge R650', 'os' => 'Windows Server 2022']],
    'NAS' => ['icon' => 'fa-hdd', 'tone' => 'storage', 'group' => 'server', 'os' => 'os',
        'fields' => ['os', 'network', 'links', 'serial', 'purchase', 'warranty'], 'specs' => ['drive_bays', 'drives', 'raw_tb', 'usable_tb', 'raid', 'ports'],
        'example' => ['name' => 'NAS-01', 'description' => 'File shares and backups', 'make' => 'Synology', 'model' => 'DS923+', 'os' => 'DSM 7.2']],
    'Virtual Machine' => ['icon' => 'fa-cloud', 'tone' => 'virtual', 'group' => 'virtual', 'os' => 'os',
        'fields' => ['os', 'network', 'nat', 'links'], 'specs' => ['vm_host', 'vcpus', 'ram_gb', 'storage'],
        'example' => ['name' => 'VM-APP-01', 'description' => 'Line of business app', 'make' => 'Microsoft', 'model' => 'Hyper-V', 'os' => 'Ubuntu 24.04']],
    'Firewall/Router' => ['icon' => 'fa-fire-alt', 'tone' => 'firewall', 'group' => 'network', 'os' => 'firmware',
        'fields' => ['os', 'network', 'nat', 'links', 'serial', 'purchase', 'warranty'], 'specs' => ['ports', 'sfp_ports'],
        'example' => ['name' => 'FW-01', 'description' => 'Edge firewall', 'make' => 'Netgate', 'model' => '6100', 'os' => 'pfSense Plus 24.11']],
    'Switch' => ['icon' => 'fa-network-wired', 'tone' => 'network', 'group' => 'network', 'os' => 'firmware',
        'fields' => ['os', 'network', 'links', 'serial', 'purchase', 'warranty'], 'specs' => ['ports', 'sfp_ports', 'poe_watts', 'managed'],
        'example' => ['name' => 'SW-CORE-01', 'description' => 'Core switch, comms closet', 'make' => 'Cisco', 'model' => 'Catalyst 9200L-24P', 'os' => 'IOS-XE 17.9']],
    'Access Point' => ['icon' => 'fa-wifi', 'tone' => 'wireless', 'group' => 'network', 'os' => 'firmware',
        'fields' => ['os', 'network', 'links', 'serial', 'purchase', 'warranty'], 'specs' => ['wifi'],
        'example' => ['name' => 'AP-LOBBY', 'description' => 'Lobby ceiling', 'make' => 'Ubiquiti', 'model' => 'U7 Pro', 'os' => 'UniFi 7.0']],
    'Modem/ONT' => ['icon' => 'fa-broadcast-tower', 'tone' => 'network', 'group' => 'network', 'os' => 'firmware',
        'fields' => ['os', 'network', 'nat', 'links', 'serial', 'purchase', 'warranty'], 'specs' => ['circuit_id', 'isp_account', 'ports'],
        'example' => ['name' => 'ONT-01', 'description' => 'Fiber handoff', 'make' => 'Nokia', 'model' => 'G-010G-A', 'os' => '3FE49362']],
    'UPS' => ['icon' => 'fa-car-battery', 'tone' => 'power', 'group' => 'power', 'os' => 'firmware',
        'fields' => ['os', 'network', 'links', 'serial', 'purchase', 'warranty'], 'specs' => ['va', 'watts', 'runtime_min', 'outlets', 'battery_installed', 'battery_replace'],
        'example' => ['name' => 'UPS-RACK-01', 'description' => 'Rack UPS, comms closet', 'make' => 'APC', 'model' => 'SMT1500RM2UC', 'os' => 'NMC 2.6']],
    'PDU' => ['icon' => 'fa-plug', 'tone' => 'power', 'group' => 'power', 'os' => 'firmware',
        'fields' => ['os', 'network', 'links', 'serial', 'purchase', 'warranty'], 'specs' => ['pdu_type', 'outlets', 'power_input'],
        'example' => ['name' => 'PDU-RACK-01', 'description' => 'Rack PDU, A side', 'make' => 'APC', 'model' => 'AP7900B', 'os' => 'RPDU 7.1']],
    'Printer' => ['icon' => 'fa-print', 'tone' => 'peripheral', 'group' => 'printer', 'os' => 'firmware',
        'fields' => ['os', 'network', 'links', 'serial', 'purchase', 'warranty'], 'specs' => ['color', 'supplies'],
        'example' => ['name' => 'PRN-FRONT', 'description' => 'Front desk printer', 'make' => 'HP', 'model' => 'LaserJet Pro M404dn', 'os' => 'FutureSmart 5.7']],
    'Phone' => ['icon' => 'fa-phone', 'tone' => 'phone', 'group' => 'mobile', 'os' => 'firmware',
        'fields' => ['contact', 'os', 'network', 'serial', 'purchase', 'warranty'], 'specs' => ['extension'],
        'example' => ['name' => 'PH-204', 'description' => 'Front desk phone', 'make' => 'Yealink', 'model' => 'T54W', 'os' => '96.86.0.55']],
    'Mobile Phone' => ['icon' => 'fa-mobile-alt', 'tone' => 'phone', 'group' => 'mobile', 'os' => 'os',
        'fields' => ['contact', 'os', 'serial', 'purchase', 'warranty'], 'specs' => ['phone_number', 'carrier', 'imei'],
        'example' => ['name' => 'MOB-CEO', 'description' => "Owner's phone", 'make' => 'Apple', 'model' => 'iPhone 15', 'os' => 'iOS 18']],
    'Tablet' => ['icon' => 'fa-tablet-alt', 'tone' => 'phone', 'group' => 'mobile', 'os' => 'os',
        'fields' => ['contact', 'os', 'serial', 'purchase', 'warranty'], 'specs' => ['phone_number', 'carrier', 'imei'],
        'example' => ['name' => 'TAB-CHECKIN', 'description' => 'Patient check-in tablet', 'make' => 'Apple', 'model' => 'iPad (10th gen)', 'os' => 'iPadOS 18']],
    'Display' => ['icon' => 'fa-tv', 'tone' => 'peripheral', 'group' => 'peripheral', 'os' => 'os',
        'fields' => ['contact', 'serial', 'purchase', 'warranty'], 'specs' => ['size_in', 'resolution'],
        'example' => ['name' => 'MON-RECEPTION', 'description' => 'Reception monitor', 'make' => 'Dell', 'model' => 'P2723D', 'os' => '']],
    'Camera' => ['icon' => 'fa-video', 'tone' => 'peripheral', 'group' => 'peripheral', 'os' => 'firmware',
        'fields' => ['os', 'network', 'links', 'serial', 'purchase', 'warranty'], 'specs' => ['resolution'],
        'example' => ['name' => 'CAM-LOBBY', 'description' => 'Lobby entrance', 'make' => 'Hikvision', 'model' => 'DS-2CD2347G2', 'os' => 'V5.7.12']],
    'NVR/DVR' => ['icon' => 'fa-photo-video', 'tone' => 'storage', 'group' => 'peripheral', 'os' => 'firmware',
        'fields' => ['os', 'network', 'links', 'serial', 'purchase', 'warranty'], 'specs' => ['channels', 'storage', 'ports'],
        'example' => ['name' => 'NVR-01', 'description' => 'Camera recorder', 'make' => 'Hikvision', 'model' => 'DS-7616NI-K2', 'os' => 'V4.62']],
    'Other' => ['icon' => 'fa-tag', 'tone' => 'other', 'group' => 'other', 'os' => 'os',
        'fields' => ASSET_FIELDS, 'specs' => [],
        'example' => ['name' => 'ASSET-01', 'description' => 'What it is for', 'make' => 'Make', 'model' => 'Model', 'os' => '']],
];
// i18n-ignore-end

/**
 * The details, by key - label, input (text, number, select, date or asset),
 * icon and, by input: unit, min, max, step, options (select), suggest (a
 * text field's suggestions), asset_types (an asset pick, of the same
 * client), placeholder, ports ('copper' or 'sfp' - a count of interfaces) and
 * flag (a date that is flagged as it nears, like a warranty); unit_only, one
 * that belongs to the one unit (an IMEI, a circuit) and isn't copied with it;
 * alone, one whose value says what it is (Managed, Wi-Fi 6), so a short line
 * of an asset's details leaves its label off.
 */
// i18n-ignore-start - labels and examples are shown through assetTypeLabel(), assetSpecLabel(), assetTypeExample() and the like
const ASSET_SPECS = [
    'cpu' => ['label' => 'CPU', 'input' => 'text', 'icon' => 'fa-microchip', 'placeholder' => 'Intel Core i7-1365U'],
    'ram_gb' => ['label' => 'RAM', 'input' => 'number', 'icon' => 'fa-memory', 'unit' => 'GB', 'min' => 0, 'max' => 1048576, 'step' => 'any', 'placeholder' => '16'],
    'storage' => ['label' => 'Storage', 'input' => 'text', 'icon' => 'fa-hdd', 'placeholder' => '512 GB NVMe SSD'],
    'vm_host' => ['label' => 'Host', 'input' => 'asset', 'icon' => 'fa-server', 'asset_types' => ['Server', 'NAS']],
    'vcpus' => ['label' => 'vCPUs', 'input' => 'number', 'icon' => 'fa-microchip', 'min' => 1, 'max' => 4096, 'step' => 1, 'placeholder' => '4'],
    'ports' => ['label' => 'Ports', 'input' => 'number', 'icon' => 'fa-ethernet', 'min' => 0, 'max' => 128, 'step' => 1, 'placeholder' => '24', 'ports' => 'copper'],
    'sfp_ports' => ['label' => 'SFP ports', 'input' => 'number', 'icon' => 'fa-ethernet', 'min' => 0, 'max' => 64, 'step' => 1, 'placeholder' => '4', 'ports' => 'sfp'],
    'poe_watts' => ['label' => 'PoE budget', 'input' => 'number', 'icon' => 'fa-bolt', 'unit' => 'W', 'min' => 0, 'max' => 100000, 'step' => 'any', 'placeholder' => '370'],
    'managed' => ['label' => 'Management', 'input' => 'select', 'icon' => 'fa-sliders-h', 'options' => ['Managed', 'Smart', 'Unmanaged'], 'alone' => true],
    'wifi' => ['label' => 'Wi-Fi', 'input' => 'select', 'icon' => 'fa-wifi', 'alone' => true, 'options' => ['Wi-Fi 4 (802.11n)', 'Wi-Fi 5 (802.11ac)', 'Wi-Fi 6 (802.11ax)', 'Wi-Fi 6E', 'Wi-Fi 7 (802.11be)']],
    'drive_bays' => ['label' => 'Drive bays', 'input' => 'number', 'icon' => 'fa-th-large', 'min' => 0, 'max' => 1000, 'step' => 1, 'placeholder' => '4'],
    'drives' => ['label' => 'Drives', 'input' => 'text', 'icon' => 'fa-hdd', 'placeholder' => '4 x 8 TB WD Red Plus'],
    'raw_tb' => ['label' => 'Raw capacity', 'input' => 'number', 'icon' => 'fa-database', 'unit' => 'TB', 'min' => 0, 'max' => 1000000, 'step' => 'any', 'placeholder' => '32'],
    'usable_tb' => ['label' => 'Usable capacity', 'input' => 'number', 'icon' => 'fa-database', 'unit' => 'TB', 'min' => 0, 'max' => 1000000, 'step' => 'any', 'placeholder' => '24'],
    'raid' => ['label' => 'RAID', 'input' => 'text', 'icon' => 'fa-layer-group', 'placeholder' => 'RAID 5, SHR-2, RAIDZ2', 'suggest' => ['RAID 1', 'RAID 5', 'RAID 6', 'RAID 10', 'SHR', 'SHR-2', 'RAIDZ1', 'RAIDZ2', 'RAIDZ3', 'JBOD']],
    'va' => ['label' => 'Capacity', 'input' => 'number', 'icon' => 'fa-bolt', 'unit' => 'VA', 'min' => 0, 'max' => 10000000, 'step' => 'any', 'placeholder' => '1500'],
    'watts' => ['label' => 'Output', 'input' => 'number', 'icon' => 'fa-plug', 'unit' => 'W', 'min' => 0, 'max' => 10000000, 'step' => 'any', 'placeholder' => '1000'],
    'runtime_min' => ['label' => 'Runtime', 'input' => 'number', 'icon' => 'fa-hourglass-half', 'unit' => 'min', 'min' => 0, 'max' => 100000, 'step' => 'any', 'placeholder' => '12'],
    'outlets' => ['label' => 'Outlets', 'input' => 'number', 'icon' => 'fa-plug', 'min' => 0, 'max' => 1000, 'step' => 1, 'placeholder' => '8'],
    'battery_installed' => ['label' => 'Battery installed', 'input' => 'date', 'icon' => 'fa-battery-full'],
    'battery_replace' => ['label' => 'Replace battery by', 'input' => 'date', 'icon' => 'fa-battery-quarter', 'flag' => true],
    'pdu_type' => ['label' => 'Kind', 'input' => 'select', 'icon' => 'fa-plug', 'options' => ['Basic', 'Metered', 'Monitored', 'Switched'], 'alone' => true],
    'power_input' => ['label' => 'Input', 'input' => 'text', 'icon' => 'fa-bolt', 'placeholder' => 'NEMA L5-30P, 30 A'],
    'circuit_id' => ['label' => 'Circuit ID', 'input' => 'text', 'icon' => 'fa-fingerprint', 'placeholder' => '12.KXFN.012345..ATI', 'unit_only' => true],
    'isp_account' => ['label' => 'ISP account', 'input' => 'text', 'icon' => 'fa-hashtag', 'placeholder' => '8301 2245 0019 4410', 'unit_only' => true],
    'channels' => ['label' => 'Channels', 'input' => 'number', 'icon' => 'fa-video', 'min' => 0, 'max' => 1024, 'step' => 1, 'placeholder' => '16'],
    'color' => ['label' => 'Prints in', 'input' => 'select', 'icon' => 'fa-palette', 'options' => ['Color', 'Black and white'], 'alone' => true],
    'supplies' => ['label' => 'Supplies', 'input' => 'text', 'icon' => 'fa-fill-drip', 'placeholder' => 'HP 58A toner'],
    'extension' => ['label' => 'Extension', 'input' => 'text', 'icon' => 'fa-phone', 'placeholder' => '204', 'unit_only' => true],
    'phone_number' => ['label' => 'Phone number', 'input' => 'text', 'icon' => 'fa-phone', 'placeholder' => '412-555-0134', 'unit_only' => true],
    'carrier' => ['label' => 'Carrier', 'input' => 'text', 'icon' => 'fa-signal', 'placeholder' => 'Verizon'],
    'imei' => ['label' => 'IMEI', 'input' => 'text', 'icon' => 'fa-sim-card', 'placeholder' => '356938035643809', 'mono' => true, 'unit_only' => true],
    'size_in' => ['label' => 'Size', 'input' => 'number', 'icon' => 'fa-expand', 'unit' => 'in', 'min' => 0, 'max' => 1000, 'step' => 'any', 'placeholder' => '27'],
    'resolution' => ['label' => 'Resolution', 'input' => 'text', 'icon' => 'fa-th', 'placeholder' => '2560 x 1440, 4 MP', 'suggest' => ['1920 x 1080', '2560 x 1440', '3840 x 2160', '2 MP', '4 MP', '8 MP']],
];
// i18n-ignore-end

/** The types that go in a rack - offered first when racking an asset, and on a rack page as kit not in one yet. */
const ASSET_RACK_TYPES = ['Firewall/Router', 'Switch', 'Server', 'NAS', 'UPS', 'PDU', 'NVR/DVR', 'Modem/ONT'];

/** The groups types fall into - for the assets list's tabs, the portal and the client overview. */
// i18n-ignore-start - labels and examples are shown through assetTypeLabel(), assetSpecLabel(), assetTypeExample() and the like
const ASSET_TYPE_GROUPS = [
    'workstation' => ['label' => 'Workstations', 'icon' => 'fa-desktop'],
    'server' => ['label' => 'Servers & storage', 'icon' => 'fa-server'],
    'virtual' => ['label' => 'Virtual', 'icon' => 'fa-cloud'],
    'network' => ['label' => 'Network', 'icon' => 'fa-network-wired'],
    'power' => ['label' => 'Power', 'icon' => 'fa-car-battery'],
    'printer' => ['label' => 'Printers', 'icon' => 'fa-print'],
    'mobile' => ['label' => 'Phones & tablets', 'icon' => 'fa-mobile-alt'],
    'peripheral' => ['label' => 'Displays & cameras', 'icon' => 'fa-tv'],
    'other' => ['label' => 'Other', 'icon' => 'fa-tag'],
];
// i18n-ignore-end

/** An asset type as people read it - the stored English name in the interface language. */
function assetTypeLabel($asset_type) {
    $asset_type = (string) $asset_type;
    return [
        'Laptop' => _x('Laptop', 'asset type'),
        'Desktop' => _x('Desktop', 'asset type'),
        'Server' => _x('Server', 'asset type'),
        'NAS' => _x('NAS', 'asset type'),
        'Virtual Machine' => _x('Virtual Machine', 'asset type'),
        'Firewall/Router' => _x('Firewall/Router', 'asset type'),
        'Switch' => _x('Switch', 'asset type'),
        'Access Point' => _x('Access Point', 'asset type'),
        'Modem/ONT' => _x('Modem/ONT', 'asset type'),
        'UPS' => _x('UPS', 'asset type'),
        'PDU' => _x('PDU', 'asset type'),
        'Printer' => _x('Printer', 'asset type'),
        'Phone' => _x('Phone', 'asset type'),
        'Mobile Phone' => _x('Mobile Phone', 'asset type'),
        'Tablet' => _x('Tablet', 'asset type'),
        'Display' => _x('Display', 'asset type'),
        'Camera' => _x('Camera', 'asset type'),
        'NVR/DVR' => _x('NVR/DVR', 'asset type'),
        'Other' => _x('Other', 'asset type'),
    ][$asset_type] ?? $asset_type;
}

/** A detail's label (ASSET_SPECS) in the interface language. */
function assetSpecLabel($key) {
    return [
        'cpu' => _x('CPU', 'asset detail'),
        'ram_gb' => _x('RAM', 'asset detail'),
        'storage' => _x('Storage', 'asset detail'),
        'vm_host' => _x('Host', 'asset detail'),
        'vcpus' => _x('vCPUs', 'asset detail'),
        'ports' => _x('Ports', 'asset detail'),
        'sfp_ports' => _x('SFP ports', 'asset detail'),
        'poe_watts' => _x('PoE budget', 'asset detail'),
        'managed' => _x('Management', 'asset detail'),
        'wifi' => _x('Wi-Fi', 'asset detail'),
        'drive_bays' => _x('Drive bays', 'asset detail'),
        'drives' => _x('Drives', 'asset detail'),
        'raw_tb' => _x('Raw capacity', 'asset detail'),
        'usable_tb' => _x('Usable capacity', 'asset detail'),
        'raid' => _x('RAID', 'asset detail'),
        'va' => _x('Capacity', 'asset detail'),
        'watts' => _x('Output', 'asset detail'),
        'runtime_min' => _x('Runtime', 'asset detail'),
        'outlets' => _x('Outlets', 'asset detail'),
        'battery_installed' => _x('Battery installed', 'asset detail'),
        'battery_replace' => _x('Replace battery by', 'asset detail'),
        'pdu_type' => _x('Kind', 'asset detail'),
        'power_input' => _x('Input', 'asset detail'),
        'circuit_id' => _x('Circuit ID', 'asset detail'),
        'isp_account' => _x('ISP account', 'asset detail'),
        'channels' => _x('Channels', 'asset detail'),
        'color' => _x('Prints in', 'asset detail'),
        'supplies' => _x('Supplies', 'asset detail'),
        'extension' => _x('Extension', 'asset detail'),
        'phone_number' => _x('Phone number', 'asset detail'),
        'carrier' => _x('Carrier', 'asset detail'),
        'imei' => _x('IMEI', 'asset detail'),
        'size_in' => _x('Size', 'asset detail'),
        'resolution' => _x('Resolution', 'asset detail'),
    ][$key] ?? (ASSET_SPECS[$key]['label'] ?? (string) $key);
}

/** One of a select detail's options (stored in English) as people read it. */
function assetSpecOptionLabel($value) {
    $value = (string) $value;
    return [
        'Managed' => _x('Managed', 'asset detail option'),
        'Smart' => _x('Smart', 'asset detail option'),
        'Unmanaged' => _x('Unmanaged', 'asset detail option'),
        'Basic' => _x('Basic', 'asset detail option'),
        'Metered' => _x('Metered', 'asset detail option'),
        'Monitored' => _x('Monitored', 'asset detail option'),
        'Switched' => _x('Switched', 'asset detail option'),
        'Color' => _x('Color', 'asset detail option'),
        'Black and white' => _x('Black and white', 'asset detail option'),
    ][$value] ?? $value;
}

/** A type group's label (ASSET_TYPE_GROUPS) in the interface language. */
function assetTypeGroupLabel($group) {
    return [
        'workstation' => __('Workstations'),
        'server' => __('Servers & storage'),
        'virtual' => __('Virtual'),
        'network' => __('Network'),
        'power' => __('Power'),
        'printer' => __('Printers'),
        'mobile' => __('Phones & tablets'),
        'peripheral' => __('Displays & cameras'),
        'other' => __('Other'),
    ][$group] ?? (ASSET_TYPE_GROUPS[$group]['label'] ?? (string) $group);
}

/** A type's example for the form's placeholders, its description in the interface language. */
function assetTypeExample($asset_type) {
    $example = assetTypeInfo($asset_type)['example'];
    $example['description'] = [
        'Laptop' => __('Front desk laptop'),
        'Desktop' => __('Reception PC'),
        'Server' => __('Domain controller for HQ'),
        'NAS' => __('File shares and backups'),
        'Virtual Machine' => __('Line of business app'),
        'Firewall/Router' => __('Edge firewall'),
        'Switch' => __('Core switch, comms closet'),
        'Access Point' => __('Lobby ceiling'),
        'Modem/ONT' => __('Fiber handoff'),
        'UPS' => __('Rack UPS, comms closet'),
        'PDU' => __('Rack PDU, A side'),
        'Printer' => __('Front desk printer'),
        'Phone' => __('Front desk phone'),
        'Mobile Phone' => __('Owner\'s phone'),
        'Tablet' => __('Patient check-in tablet'),
        'Display' => __('Reception monitor'),
        'Camera' => __('Lobby entrance'),
        'NVR/DVR' => __('Camera recorder'),
        'Other' => __('What it is for'),
    ][assetTypeInfo($asset_type)['name']] ?? $example['description'];
    if (assetTypeInfo($asset_type)['name'] === 'Other') {
        $example['make'] = __('Make');
        $example['model'] = __('Model');
    }
    return $example;
}

/** A type's entry - Other's for a type that isn't one ITFlow knows, with its name. */
function assetTypeInfo($asset_type) {
    $asset_type = (string) $asset_type;
    if (!isset(ASSET_TYPES[$asset_type])) {
        // Spelt another way - 'switch', as the database matches it too - or not one at all
        $asset_type = assetTypeName($asset_type) ?: 'Other';
    }
    return ASSET_TYPES[$asset_type] + ['name' => $asset_type];
}

/** A type as the list spells it, matched without case - '' when it isn't one. */
function assetTypeName($value) {
    $value = trim((string) $value);
    foreach (array_keys(ASSET_TYPES) as $asset_type) {
        if (strcasecmp($asset_type, $value) === 0) {
            return $asset_type;
        }
    }
    return '';
}

/** Type => icon class (fa-laptop), in order - what $asset_types_array has always been. */
function assetTypeIcons() {
    return array_map(function ($type) {
        return $type['icon'];
    }, ASSET_TYPES);
}

/** The types in one or more groups. */
function assetTypesInGroup(...$groups) {
    return array_keys(array_filter(ASSET_TYPES, function ($type) use ($groups) {
        return in_array($type['group'], $groups, true);
    }));
}

/**
 * SQL that matches a group's types on $column - 'other' is also anything
 * that isn't a type ITFlow knows (an old or hand-made one).
 */
function assetTypeGroupSql($group, $column = 'asset_type') {
    $in = function (array $types) {
        return "'" . implode("', '", array_map('escapeSql', $types)) . "'";
    };
    if ($group === 'other') {
        $known = array_diff(array_keys(ASSET_TYPES), assetTypesInGroup('other'));
        return "$column NOT IN (" . $in($known) . ")"; // i18n-ignore - SQL
    }
    $types = assetTypesInGroup($group);
    return $types ? "$column IN (" . $in($types) . ")" : '1 = 0'; // i18n-ignore - SQL
}

/**
 * The assets list's tabs - key => label, icon, what New calls one, and the
 * SQL on asset_type. Other is every type the rest don't take, and any type
 * ITFlow doesn't know. Shared with the export, which takes the same filter.
 */
function assetTypeTabs($column = 'asset_type') {
    $tabs = [
        'workstation' => ['label' => __('Workstations'), 'new' => __('New Workstation'), 'groups' => ['workstation']],
        'server' => ['label' => __('Servers & NAS'), 'new' => __('New Server'), 'groups' => ['server']],
        'virtual' => ['label' => __('Virtual'), 'new' => __('New Virtual Machine'), 'groups' => ['virtual']],
        'network' => ['label' => __('Network'), 'new' => __('New Network Device'), 'groups' => ['network']],
        'power' => ['label' => __('Power'), 'new' => __('New UPS'), 'groups' => ['power']],
        'printer' => ['label' => __('Printers'), 'new' => __('New Printer'), 'groups' => ['printer']],
    ];
    $taken = [];
    foreach ($tabs as $key => $tab) {
        $types = assetTypesInGroup(...$tab['groups']);
        $taken = array_merge($taken, $types);
        $tabs[$key]['icon'] = ASSET_TYPE_GROUPS[$tab['groups'][0]]['icon'];
        $tabs[$key]['query'] = "$column IN ('" . implode("', '", array_map('escapeSql', $types)) . "')"; // i18n-ignore - SQL
    }
    $tabs['other'] = ['label' => __('Other'), 'new' => __('New Asset'), 'groups' => ['mobile', 'peripheral', 'other'], 'icon' => 'fa-tag',
        'query' => "$column NOT IN ('" . implode("', '", array_map('escapeSql', $taken)) . "')"]; // i18n-ignore - SQL
    return $tabs;
}

/** Does this type show the field (from ASSET_FIELDS)? */
function assetTypeHasField($asset_type, $field) {
    return in_array($field, assetTypeInfo($asset_type)['fields'], true);
}

/** What the OS field is called on this type - "Operating system" or "Firmware". */
function assetTypeOsLabel($asset_type) {
    return assetTypeInfo($asset_type)['os'] === 'firmware' ? __('Firmware') : __('Operating system');
}

/** A type's details - key => ASSET_SPECS entry, in its order. */
function assetTypeSpecs($asset_type) {
    $specs = [];
    foreach (assetTypeInfo($asset_type)['specs'] as $key) {
        $specs[$key] = ['label' => assetSpecLabel($key)] + ASSET_SPECS[$key];
    }
    return $specs;
}

/**
 * Is the asset_specs table there yet (database update 3.4.9)? Until it is,
 * no asset has details - none are read, kept or flagged.
 */
function assetSpecsReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        $sql = mysqli_query($mysqli, "SHOW TABLES LIKE 'asset_specs'");
        $ready = $sql && mysqli_num_rows($sql) > 0;
    }
    return $ready;
}

/** Each asset's details - [asset_id => [key => value]], raw. */
function assetSpecsFor(array $asset_ids) {
    global $mysqli;
    $asset_ids = array_values(array_unique(array_filter(array_map('intval', $asset_ids))));
    $specs = array_fill_keys($asset_ids, []);
    if (!$asset_ids || !assetSpecsReady()) {
        return $specs;
    }
    $sql = mysqli_query($mysqli, "SELECT asset_spec_asset_id, asset_spec_key, asset_spec_value FROM asset_specs WHERE asset_spec_asset_id IN (" . implode(',', $asset_ids) . ")");
    while ($sql && $row = mysqli_fetch_assoc($sql)) {
        $specs[intval($row['asset_spec_asset_id'])][$row['asset_spec_key']] = $row['asset_spec_value'];
    }
    return $specs;
}

/** One asset's details, raw - [key => value]. */
function assetSpecs($asset_id) {
    return assetSpecsFor([$asset_id])[intval($asset_id)] ?? [];
}

/** A number as it was meant - 16, not 16.00; 1.5 as typed. */
function assetSpecNumber($value) {
    $value = (float) $value;
    return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
}

/**
 * What was given for a type's details, checked - [key => value] of the ones
 * with a value, as they are to be kept. $problems gets a line for each one
 * that isn't a value its field takes, which is left out. An asset pick has
 * to be one of $client_id's own of the types the detail allows, and not the
 * asset itself.
 */
function assetSpecsFromInput($asset_type, $input, $client_id, $asset_id = 0, &$problems = []) {
    global $mysqli;
    $problems = [];
    $input = is_array($input) ? $input : [];
    $kept = [];
    foreach (assetTypeSpecs($asset_type) as $key => $spec) {
        if (!array_key_exists($key, $input) || is_array($input[$key])) {
            continue;
        }
        $value = trim((string) $input[$key]);
        if ($value === '') {
            continue;
        }
        switch ($spec['input']) {
            case 'number':
                $number = str_replace(',', '', $value);
                if (!is_numeric($number) || (float) $number < ($spec['min'] ?? 0) || (float) $number > ($spec['max'] ?? PHP_INT_MAX)
                    || (($spec['step'] ?? 'any') === 1 && floor((float) $number) != (float) $number)) {
                    $problems[] = (($spec['step'] ?? 'any') === 1 ? __('%1$s: a whole number from %2$s to %3$s', $spec['label'], $spec['min'] ?? 0, $spec['max'] ?? '') : __('%1$s: a number from %2$s to %3$s', $spec['label'], $spec['min'] ?? 0, $spec['max'] ?? '')) . (isset($spec['unit']) ? ' ' . $spec['unit'] : '');
                    continue 2;
                }
                $value = assetSpecNumber($number);
                break;
            case 'date':
                $date = DateTime::createFromFormat('!Y-m-d', $value);
                if (!$date || $date->format('Y-m-d') !== $value) {
                    $problems[] = __('%s: a date', $spec['label']);
                    continue 2;
                }
                break;
            case 'select':
                $match = '';
                foreach ($spec['options'] as $option) {
                    if (strcasecmp($option, $value) === 0) {
                        $match = $option;
                    }
                }
                if ($match === '') {
                    $problems[] = __('%1$s: one of %2$s', $spec['label'], implode(', ', array_map('assetSpecOptionLabel', $spec['options'])));
                    continue 2;
                }
                $value = $match;
                break;
            case 'asset':
                // By id, or by name - as an import or the export has it
                $types = "'" . implode("', '", array_map('escapeSql', $spec['asset_types'])) . "'";
                $pick = ctype_digit($value) ? intval($value) : 0;
                if (!$pick) {
                    $named = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_id FROM assets WHERE asset_name = '" . escapeSql($value) . "' AND asset_client_id = " . intval($client_id) . " AND asset_type IN ($types) AND asset_archived_at IS NULL ORDER BY asset_id LIMIT 1"));
                    $pick = intval($named['asset_id'] ?? 0);
                }
                if (!$pick || $pick === intval($asset_id) || !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM assets WHERE asset_id = $pick AND asset_client_id = " . intval($client_id) . " AND asset_type IN ($types) LIMIT 1"))) {
                    $problems[] = __('%1$s: one of the client\'s assets of type %2$s', $spec['label'], implode(', ', array_map('assetTypeLabel', $spec['asset_types'])));
                    continue 2;
                }
                $value = (string) $pick;
                break;
            default:
                $value = mb_substr(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value), 0, 255);
        }
        $kept[$key] = $value;
    }
    return $kept;
}

/**
 * Keep an asset's details - what its type has, as given; the rest go (a
 * detail its type no longer has, or one emptied). What changed, in words.
 */
function assetSpecsSave($asset_id, $asset_type, array $specs) {
    global $mysqli;
    $asset_id = intval($asset_id);
    if (!assetSpecsReady()) {
        return [];
    }
    $before = assetSpecs($asset_id);
    $allowed = array_keys(assetTypeSpecs($asset_type));
    $changes = [];
    foreach ($before as $key => $value) {
        if (!in_array($key, $allowed, true) || !array_key_exists($key, $specs)) {
            mysqli_query($mysqli, "DELETE FROM asset_specs WHERE asset_spec_asset_id = $asset_id AND asset_spec_key = '" . escapeSql($key) . "'");
            if (in_array($key, $allowed, true)) {
                $changes[] = (ASSET_SPECS[$key]['label'] ?? $key) . ' cleared';
            }
        }
    }
    foreach ($specs as $key => $value) {
        if (!in_array($key, $allowed, true) || ($before[$key] ?? null) === (string) $value) {
            continue;
        }
        mysqli_query($mysqli, "INSERT INTO asset_specs SET asset_spec_asset_id = $asset_id, asset_spec_key = '" . escapeSql($key) . "', asset_spec_value = '" . escapeSql($value) . "'
            ON DUPLICATE KEY UPDATE asset_spec_value = VALUES(asset_spec_value)");
        $changes[] = ASSET_SPECS[$key]['label'] . ' ' . assetSpecText($key, $value, ['link' => false]);
    }
    return $changes;
}

/**
 * An asset's ports from its details - the interfaces its ports and SFP ports
 * count that it doesn't have yet are made (interfacePortsEnsure()). How many,
 * by kind - copper and sfp.
 */
function assetPortsFromSpecs($asset_id, $asset_type, array $specs) {
    $type_specs = assetTypeSpecs($asset_type);
    if (!isset($type_specs['ports']) && !isset($type_specs['sfp_ports'])) {
        return ['copper' => 0, 'sfp' => 0];
    }
    return interfacePortsEnsure($asset_id, $asset_type, intval($specs['ports'] ?? 0), intval($specs['sfp_ports'] ?? 0));
}

/** "23 ports and 4 SFP ports" - what assetPortsFromSpecs() made, or ''. */
function assetPortsMadeText(array $made) {
    $parts = [];
    if ($made['copper']) {
        $parts[] = _n('%d port', '%d ports', $made['copper']);
    }
    if ($made['sfp']) {
        $parts[] = _n('%d SFP port', '%d SFP ports', $made['sfp']);
    }
    return implode(' ' . __('and') . ' ', $parts);
}

/**
 * The asset form's details (spec[key]) for an asset just saved: checked,
 * kept, and its ports made from their counts - when a count is new or
 * changed, so an edit of something else doesn't put back a port someone
 * took off. [what to add to the message - ", with 23 ports added" or '',
 * the values not kept and why].
 */
function assetSpecsFromPost($asset_id, $asset_type, $input, $client_id) {
    $asset_type = assetTypeName($asset_type) ?: trim((string) $asset_type);
    $before = assetSpecs($asset_id);
    $specs = assetSpecsFromInput($asset_type, $input, $client_id, $asset_id, $problems);
    assetSpecsSave($asset_id, $asset_type, $specs);
    $made = '';
    if (($before['ports'] ?? '') !== ($specs['ports'] ?? '') || ($before['sfp_ports'] ?? '') !== ($specs['sfp_ports'] ?? '')) {
        $made = assetPortsMadeText(assetPortsFromSpecs($asset_id, $asset_type, $specs));
    }
    return [$made !== '' ? __(', with %s added', $made) : '', $problems];
}

/**
 * A detail as it reads - "16 GB", "Wi-Fi 6 (802.11ax)", a date. A host is its
 * name ($opts['hosts'] => [id => name] saves a lookup each; $opts['link']
 * false gives plain text, the default HTML is escaped either way). Raw text.
 */
function assetSpecText($key, $value, array $opts = []) {
    $spec = ASSET_SPECS[$key] ?? ['input' => 'text'];
    $value = (string) $value;
    if ($value === '') {
        return '';
    }
    switch ($spec['input']) {
        case 'number':
            return assetSpecNumber($value) . (isset($spec['unit']) ? ' ' . $spec['unit'] : '');
        case 'asset':
            $hosts = $opts['hosts'] ?? null;
            $name = $hosts !== null ? ($hosts[intval($value)] ?? '') : (string) getFieldById('assets', intval($value), 'asset_name');
            return $name !== '' ? $name : __('An asset that is gone');
        case 'select':
            return assetSpecOptionLabel($value);
        default:
            return $value;
    }
}

/**
 * The details a page shows for an asset - [key => [label, icon, text, html,
 * flag]] in its type's order, the ones with a value. html is ready to echo: a
 * host links to its page (the same client's only), a flagged date carries its
 * badge as assetWarranty() reads it. Ports read "24 + 4 SFP".
 */
function assetSpecRows($asset_type, array $specs, $client_id = 0) {
    $rows = [];
    $type_specs = assetTypeSpecs($asset_type);
    foreach ($type_specs as $key => $spec) {
        $value = (string) ($specs[$key] ?? '');
        if ($value === '' || ($key === 'sfp_ports' && isset($type_specs['ports']) && ($specs['ports'] ?? '') !== '')) {
            continue;
        }
        $text = assetSpecText($key, $value);
        $html = escapeHtml($text);
        $flag = null;
        if ($key === 'ports' && ($specs['sfp_ports'] ?? '') !== '') {
            $text = assetSpecNumber($value) . ' + ' . assetSpecNumber($specs['sfp_ports']) . ' SFP';
            $html = escapeHtml($text);
        } elseif ($spec['input'] === 'asset') {
            $host_id = intval($value);
            $host = $host_id ? mysqli_fetch_assoc(mysqli_query($GLOBALS['mysqli'], "SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $host_id LIMIT 1")) : null;
            if (!$host || ($client_id && intval($host['asset_client_id']) !== intval($client_id))) {
                continue;
            }
            $text = $host['asset_name'];
            $html = '<a href="asset.php?client_id=' . intval($host['asset_client_id']) . '&amp;asset_id=' . $host_id . '">' . escapeHtml($text) . '</a>';
        } elseif (!empty($spec['flag'])) {
            $flag = assetWarranty($value);
            if ($flag) {
                $flag['short'] = $flag['tone'] === 'success' ? __('Good') : ($flag['tone'] === 'danger' ? __('Overdue') : $flag['short']);
                $flag['text'] = $flag['tone'] === 'danger' ? __('Due %s', timeAgo($flag['date'])) : ($flag['tone'] === 'warning' ? ($flag['days'] === 0 ? __('Due today') : _n('Due in %d day', 'Due in %d days', $flag['days'])) : __('By %s', formatDate($flag['date'], 'date')));
            }
        } elseif (!empty($spec['mono'])) {
            $html = '<span class="font-monospace">' . $html . '</span>';
        }
        // The short line - the list's Details, the quick look: the label, unless the value says what it is, and no age
        $label = $spec['label'];
        $alone = !empty($spec['alone']) || stripos($text, $label) === 0;
        $brief = ($alone ? '' : '<span class="text-body-secondary">' . escapeHtml($label) . '</span> ') . $html;
        if ($spec['input'] === 'date' && !$flag && !in_array($age = assetAge($value), ['', __('new')], true)) {
            $html .= '<div class="small text-body-secondary">' . escapeHtml(__('%s ago', $age)) . '</div>';
        }
        $rows[$key] = ['label' => $label, 'icon' => $spec['icon'], 'text' => $text, 'html' => $html, 'flag' => $flag, 'alone' => $alone, 'brief' => $brief];
    }
    return $rows;
}

/** The assets a detail can pick (a VM's host) - the client's own of its types, bar this one: [id => name]. */
function assetSpecAssetOptions($key, $client_id, $asset_id = 0, $keep_id = 0) {
    global $mysqli;
    $spec = ASSET_SPECS[$key] ?? null;
    if (!$spec || $spec['input'] !== 'asset' || !$client_id) {
        return [];
    }
    $types = "'" . implode("', '", array_map('escapeSql', $spec['asset_types'])) . "'";
    $options = [];
    $sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type FROM assets WHERE asset_client_id = " . intval($client_id) . " AND asset_type IN ($types)
        AND asset_id <> " . intval($asset_id) . " AND (asset_archived_at IS NULL OR asset_id = " . intval($keep_id) . ") ORDER BY asset_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $options[intval($row['asset_id'])] = $row;
    }
    return $options;
}

/** The virtual machines that run on an asset (their Host) - the same client's, not archived: [id => name]. */
function assetGuests($asset_id, $client_id) {
    global $mysqli;
    $guests = [];
    if (!assetSpecsReady()) {
        return $guests;
    }
    $sql = mysqli_query($mysqli, "SELECT asset_id, asset_name FROM asset_specs JOIN assets ON asset_id = asset_spec_asset_id
        WHERE asset_spec_key = 'vm_host' AND asset_spec_value = '" . intval($asset_id) . "' AND asset_client_id = " . intval($client_id) . "
        AND asset_type = 'Virtual Machine' AND asset_archived_at IS NULL ORDER BY asset_name ASC");
    while ($sql && $row = mysqli_fetch_assoc($sql)) {
        $guests[intval($row['asset_id'])] = $row['asset_name'];
    }
    return $guests;
}

/**
 * Rows for an export, each with its details as spec_<key> - its type's, as
 * written in the form (a host by its name); '' for the rest.
 */
function assetSpecsExportAttach(array $rows) {
    global $mysqli;
    $specs = assetSpecsFor(array_column($rows, 'asset_id'));
    $hosts = [];
    foreach ($specs as $asset_specs) {
        if (!empty($asset_specs['vm_host'])) {
            $hosts[intval($asset_specs['vm_host'])] = '';
        }
    }
    if ($hosts) {
        $sql = mysqli_query($mysqli, "SELECT asset_id, asset_name FROM assets WHERE asset_id IN (" . implode(',', array_keys($hosts)) . ")");
        while ($row = mysqli_fetch_assoc($sql)) {
            $hosts[intval($row['asset_id'])] = $row['asset_name'];
        }
    }
    foreach ($rows as $i => $row) {
        $type_specs = assetTypeSpecs($row['asset_type'] ?? '');
        foreach (array_keys(ASSET_SPECS) as $key) {
            $value = isset($type_specs[$key]) ? (string) ($specs[intval($row['asset_id'] ?? 0)][$key] ?? '') : '';
            $rows[$i]['spec_' . $key] = $key === 'vm_host' && $value !== '' ? ($hosts[intval($value)] ?? '') : $value;
        }
    }
    return $rows;
}

/** The API's assets, each with its details as asset_specs - {key: value}, its type's only. */
function assetSpecsApiAttach(array $rows) {
    $specs = assetSpecsFor(array_column($rows, 'asset_id'));
    foreach ($rows as $i => $row) {
        $kept = array_intersect_key($specs[intval($row['asset_id'] ?? 0)] ?? [], assetTypeSpecs($row['asset_type'] ?? ''));
        $rows[$i]['asset_specs'] = (object) $kept;
    }
    return $rows;
}
