<?php

/**
 * Attribute and option seed data (docs/architecture/04-category-tree.md §2–§4, §6; task T3.3).
 * OWNER REVIEW (C2): the vehicle and phone makes/models are a starting list compiled for launch;
 * "Other" with free text is always available, and admins can add, rename or retire options.
 *
 * Format
 *   attributes: key => [label, type, unit?, dimension?, rules?, other?, depends_on?, options?]
 *     options: list of labels (value = slug of the label), or [label, 'scope' => [ISO2…]];
 *     a dependent select lists its options per parent option value: ['toyota' => [...], …].
 *   links: category slug => list of [key, flags…] in form order. Flags: required, card, filter,
 *     'required_for' => [conditions], 'scope' => [ISO2…].
 *   unit_price: category slug => area attribute key used for the calculated price per unit.
 * Seeded once: existing attributes, options and links are never overwritten (admin edits survive).
 */
$years = ['min' => 1950, 'max_years_ahead' => 1];
$counts = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10 or more'];
$conditionsWithUse = ['used', 'reconditioned'];
$cropCountries = ['IN', 'LK'];

// ---- Shared link sets ---------------------------------------------------------------------------
$car = [
    ['make', 'required', 'card', 'filter'],
    ['model', 'required', 'card', 'filter'],
    ['year_of_manufacture', 'required', 'card', 'filter'],
    ['year_of_registration', 'filter'],
    ['mileage', 'card', 'filter', 'required_for' => $conditionsWithUse],
    ['fuel_type', 'required', 'card', 'filter'],
    ['transmission', 'required', 'card', 'filter'],
    ['engine_capacity', 'filter'],
    ['body_type', 'filter'],
    ['colour'],
    ['trim_edition'],
];
$commercialVehicle = array_values(array_filter($car, fn (array $link) => $link[0] !== 'body_type'));
$motorcycle = [
    ['motorcycle_make', 'required', 'card', 'filter'],
    ['motorcycle_model', 'required', 'card', 'filter'],
    ['year_of_manufacture', 'required', 'card', 'filter'],
    ['year_of_registration', 'filter'],
    ['mileage', 'card', 'filter', 'required_for' => $conditionsWithUse],
    ['fuel_type', 'filter'],
    ['engine_capacity', 'card', 'filter'],
    ['colour'],
];
$threeWheeler = [
    ['three_wheeler_make', 'required', 'card', 'filter'],
    ['three_wheeler_model', 'required', 'card', 'filter'],
    ['year_of_manufacture', 'required', 'card', 'filter'],
    ['year_of_registration', 'filter'],
    ['mileage', 'card', 'filter', 'required_for' => $conditionsWithUse],
    ['fuel_type', 'required', 'card', 'filter'],
    ['engine_capacity', 'filter'],
    ['colour'],
];
$deed = [['deed_type', 'filter', 'scope' => ['LK']], ['clear_deed', 'scope' => ['LK']], ['tenure', 'filter']];
$parts = [['part_category', 'required', 'card', 'filter'], ['make', 'filter'], ['compatible_models'], ['brand', 'filter'], ['part_origin', 'card', 'filter']];
$phone = [
    ['phone_brand', 'required', 'card', 'filter'],
    ['phone_model', 'required', 'card', 'filter'],
    ['storage', 'card', 'filter'],
    ['ram', 'filter'],
    ['colour'],
    ['network_sim'],
    ['warranty', 'filter'],
    ['box_included'],
];
$wearable = [['phone_brand', 'required', 'card', 'filter'], ['phone_model', 'card', 'filter'], ['colour'], ['warranty', 'filter']];
$computer = [
    ['brand', 'card', 'filter'],
    ['model_name'],
    ['processor', 'card', 'filter'],
    ['ram', 'card', 'filter'],
    ['storage', 'filter'],
    ['storage_type', 'filter'],
    ['graphics'],
    ['warranty', 'filter'],
];
$branded = [['brand', 'card', 'filter'], ['model_name'], ['warranty', 'filter']];
$brandOnly = [['brand', 'filter']];

return [
    'attributes' => [
        // ---- Shared -----------------------------------------------------------------------------
        'brand' => ['label' => 'Brand', 'type' => 'select', 'other' => true, 'options' => [
            'Acer', 'Adidas', 'Apple', 'Asus', 'Bose', 'Bosch', 'Bridgestone', 'Canon', 'Carrier', 'Casio', 'Daikin',
            'Dell', 'DJI', 'Dunlop', 'Electrolux', 'Fujifilm', 'Garmin', 'Haier', 'Hisense', 'Hitachi', 'HP', 'Huawei',
            'JBL', 'Kenwood', 'Lenovo', 'LG', 'Logitech', 'Michelin', 'Microsoft', 'Midea', 'MSI', 'Nike', 'Nikon',
            'Nintendo', 'Panasonic', 'Philips', 'Pioneer', 'Puma', 'Samsung', 'Seiko', 'Sharp', 'Singer', 'Sony',
            'TCL', 'Toshiba', 'Whirlpool', 'Xiaomi', 'Yokohama',
        ]],
        'model_name' => ['label' => 'Model', 'type' => 'text', 'rules' => ['maxlength' => 80]],
        'warranty' => ['label' => 'Warranty', 'type' => 'select', 'options' => ['No warranty', 'Shop warranty', 'Company warranty']],
        'colour' => ['label' => 'Colour', 'type' => 'select', 'other' => true, 'options' => [
            'Black', 'White', 'Silver', 'Grey', 'Blue', 'Red', 'Green', 'Gold', 'Yellow', 'Orange', 'Brown', 'Purple', 'Pink', 'Beige',
        ]],

        // ---- Vehicles ---------------------------------------------------------------------------
        'make' => ['label' => 'Make', 'type' => 'select', 'other' => true, 'options' => [
            'Toyota', 'Suzuki', 'Honda', 'Nissan', 'Mitsubishi', 'Mazda', ['Micro', 'scope' => ['LK']], 'Perodua', 'Kia', 'Hyundai',
            'BMW', 'Mercedes-Benz', 'Audi', 'Tata', 'Mahindra', 'Daihatsu', 'Subaru', 'Isuzu', 'Ford', 'Volkswagen',
            'Land Rover', 'Lexus', 'Peugeot', 'Renault', 'Chevrolet', 'BYD', 'MG', 'Proton', 'Jeep', 'Volvo',
            'Ashok Leyland', 'Hino', 'Fuso',
        ]],
        'model' => ['label' => 'Model', 'type' => 'select', 'other' => true, 'depends_on' => 'make', 'options' => [
            'toyota' => ['Aqua', 'Axio', 'Allion', 'Premio', 'Corolla', 'Corolla Cross', 'Vitz', 'Yaris', 'Prius', 'Camry', 'C-HR', 'RAV4', 'Harrier', 'Land Cruiser', 'Land Cruiser Prado', 'Fortuner', 'Hilux', 'HiAce', 'Noah', 'Voxy', 'Alphard', 'Rush', 'Raize', 'Roomy', 'Passo', 'Belta'],
            'suzuki' => ['Alto', 'Wagon R', 'Swift', 'Celerio', 'Spacia', 'Hustler', 'Every', 'Ertiga', 'Baleno', 'Dzire', 'S-Presso', 'Ciaz', 'Vitara', 'Grand Vitara', 'Jimny', 'XL6'],
            'honda' => ['Vezel', 'Fit', 'Grace', 'Civic', 'City', 'Insight', 'Freed', 'Shuttle', 'Accord', 'CR-V', 'HR-V', 'WR-V', 'Jazz', 'N-Box'],
            'nissan' => ['Leaf', 'Sunny', 'Bluebird Sylphy', 'March', 'Tiida', 'Note', 'Dayz', 'Juke', 'Kicks', 'Qashqai', 'X-Trail', 'Serena', 'Caravan', 'Navara', 'Patrol'],
            'mitsubishi' => ['Mirage', 'Attrage', 'Lancer', 'eK Wagon', 'Xpander', 'ASX', 'Eclipse Cross', 'Outlander', 'Pajero', 'Montero', 'L200', 'Triton', 'Delica'],
            'mazda' => ['Demio', 'Mazda2', 'Axela', 'Mazda3', 'Mazda6', 'CX-3', 'CX-30', 'CX-5', 'BT-50', 'Bongo'],
            'micro' => ['Panda', 'Trend', 'MX7', 'Emgrand 7'],
            'perodua' => ['Kancil', 'Viva', 'Axia', 'Bezza', 'Myvi', 'Alza', 'Ativa', 'Aruz'],
            'kia' => ['Picanto', 'Rio', 'Cerato', 'Stonic', 'Sonet', 'Seltos', 'Sportage', 'Sorento', 'Niro', 'EV6', 'Carnival'],
            'hyundai' => ['Eon', 'i10', 'Grand i10', 'i20', 'Accent', 'Elantra', 'Sonata', 'Venue', 'Creta', 'Kona', 'Tucson', 'Santa Fe', 'Ioniq 5', 'H-1'],
            'bmw' => ['1 Series', '2 Series', '3 Series', '5 Series', '7 Series', 'X1', 'X3', 'X5', 'X6', 'i3', 'iX'],
            'mercedes-benz' => ['A-Class', 'C-Class', 'E-Class', 'S-Class', 'CLA', 'GLA', 'GLC', 'GLE', 'GLS', 'G-Class'],
            'audi' => ['A3', 'A4', 'A5', 'A6', 'A8', 'Q2', 'Q3', 'Q5', 'Q7', 'Q8', 'e-tron'],
            'tata' => ['Nano', 'Indica', 'Indigo', 'Tiago', 'Tigor', 'Altroz', 'Punch', 'Nexon', 'Harrier', 'Safari', 'Xenon', 'Ace', 'Super Ace'],
            'mahindra' => ['KUV100', 'XUV300', 'XUV500', 'XUV700', 'Bolero', 'Bolero Pickup', 'Scorpio', 'Scorpio-N', 'Thar', 'Marazzo', 'Maxximo', 'Jeep (classic)'],
            'daihatsu' => ['Mira', 'Move', 'Tanto', 'Boon', 'Terios', 'Rocky', 'Hijet', 'Gran Max'],
            'subaru' => ['Impreza', 'XV', 'Forester', 'Outback', 'Legacy', 'BRZ', 'WRX'],
            'isuzu' => ['D-Max', 'MU-X', 'Elf'],
            'ford' => ['Fiesta', 'Focus', 'EcoSport', 'Everest', 'Explorer', 'Ranger', 'F-150', 'Mustang', 'Transit'],
            'volkswagen' => ['Polo', 'Vento', 'Golf', 'Jetta', 'Passat', 'Beetle', 'Tiguan', 'Touareg', 'Amarok'],
            'land-rover' => ['Defender', 'Discovery', 'Discovery Sport', 'Freelander', 'Range Rover', 'Range Rover Sport', 'Range Rover Evoque', 'Range Rover Velar'],
            'lexus' => ['CT', 'IS', 'ES', 'GS', 'LS', 'UX', 'NX', 'RX', 'LX'],
            'peugeot' => ['208', '301', '308', '2008', '3008', '5008'],
            'renault' => ['Kwid', 'Triber', 'Clio', 'Captur', 'Megane', 'Duster'],
            'chevrolet' => ['Spark', 'Aveo', 'Cruze', 'Captiva', 'Trailblazer', 'Camaro', 'Silverado'],
            'byd' => ['Dolphin', 'Atto 3', 'Seal', 'Han', 'Tang', 'M6'],
            'mg' => ['MG3', 'MG4', 'MG5', 'ZS', 'ZS EV', 'HS', 'Hector'],
            'proton' => ['Saga', 'Persona', 'Iriz', 'Exora', 'X50', 'X70'],
            'jeep' => ['Renegade', 'Compass', 'Cherokee', 'Grand Cherokee', 'Wrangler'],
            'volvo' => ['S60', 'S90', 'XC40', 'XC60', 'XC90'],
            'ashok-leyland' => ['Dost', 'Partner', 'Ecomet', 'Viking', 'Falcon'],
            'hino' => ['Dutro', '300 Series', '500 Series', '700 Series', 'Ranger'],
            'fuso' => ['Canter', 'Fighter', 'Rosa'],
        ]],
        'motorcycle_make' => ['label' => 'Make', 'type' => 'select', 'other' => true, 'options' => [
            'Bajaj', 'TVS', 'Hero', 'Honda', 'Yamaha', 'Suzuki', 'Kawasaki', 'Royal Enfield', 'KTM', 'Harley-Davidson', 'Vespa',
        ]],
        'motorcycle_model' => ['label' => 'Model', 'type' => 'select', 'other' => true, 'depends_on' => 'motorcycle_make', 'options' => [
            'bajaj' => ['CT 100', 'Platina', 'Discover 125', 'Pulsar 150', 'Pulsar N160', 'Pulsar NS200', 'Avenger', 'Dominar 400'],
            'tvs' => ['Metro', 'Star City+', 'Raider 125', 'Apache RTR 160', 'Apache RTR 200', 'Scooty Pep+', 'Wego', 'Jupiter', 'Ntorq 125'],
            'hero' => ['HF Deluxe', 'Splendor Plus', 'Passion Pro', 'Glamour', 'Hunk', 'Xtreme 160R', 'Dash', 'Pleasure+', 'Maestro Edge'],
            'honda' => ['CD 70', 'CB Shine', 'CB 125F', 'Unicorn', 'CB Hornet 160R', 'XR 150L', 'CBR 150R', 'Dio', 'Activa'],
            'yamaha' => ['YBR 125', 'FZ', 'FZ-S', 'MT-15', 'R15', 'Ray ZR', 'Fascino 125', 'NMax', 'Aerox'],
            'suzuki' => ['GN 125', 'Gixxer', 'Access 125', 'Burgman Street', 'Hayabusa'],
            'kawasaki' => ['KLX 150', 'Ninja 300', 'Ninja 400', 'Z650', 'Versys 650'],
            'royal-enfield' => ['Bullet 350', 'Classic 350', 'Hunter 350', 'Meteor 350', 'Himalayan', 'Interceptor 650', 'Continental GT 650'],
            'ktm' => ['Duke 200', 'Duke 390', 'RC 200', 'RC 390', 'Adventure 390'],
            'harley-davidson' => ['X440', 'Iron 883', 'Sportster S', 'Fat Boy', 'Street Glide'],
            'vespa' => ['VXL 125', 'SXL 150', 'LX 125', 'Primavera', 'GTS 300'],
        ]],
        'three_wheeler_make' => ['label' => 'Make', 'type' => 'select', 'other' => true, 'options' => ['Bajaj', 'TVS', 'Piaggio', 'Mahindra']],
        'three_wheeler_model' => ['label' => 'Model', 'type' => 'select', 'other' => true, 'depends_on' => 'three_wheeler_make', 'options' => [
            'bajaj' => ['RE (2-stroke)', 'RE (4-stroke)', 'RE Compact', 'Maxima'],
            'tvs' => ['King', 'King Deluxe', 'King Duramax'],
            'piaggio' => ['Ape City', 'Ape Xtra'],
            'mahindra' => ['Alfa', 'Treo'],
        ]],
        'machinery_make' => ['label' => 'Make', 'type' => 'select', 'other' => true, 'options' => [
            'JCB', 'Caterpillar', 'Komatsu', 'Hitachi', 'Kubota', 'Mahindra', 'John Deere', 'Massey Ferguson', 'Volvo', 'Yanmar',
        ]],
        'year_of_manufacture' => ['label' => 'Year of manufacture', 'type' => 'year', 'rules' => $years],
        'year_of_registration' => ['label' => 'Year of registration', 'type' => 'year', 'rules' => $years, 'help' => 'Leave empty if the vehicle is not registered yet.'],
        'mileage' => ['label' => 'Mileage', 'type' => 'number', 'unit' => 'km', 'rules' => ['min' => 0, 'max' => 2000000, 'step' => 1]],
        'hours_used' => ['label' => 'Hours used', 'type' => 'number', 'unit' => 'h', 'rules' => ['min' => 0, 'max' => 200000, 'step' => 1]],
        'fuel_type' => ['label' => 'Fuel type', 'type' => 'select', 'options' => ['Petrol', 'Diesel', 'Hybrid', 'Electric', 'Gas']],
        'transmission' => ['label' => 'Transmission', 'type' => 'select', 'options' => ['Automatic', 'Manual', 'Tiptronic']],
        'engine_capacity' => ['label' => 'Engine capacity', 'type' => 'number', 'unit' => 'cc', 'rules' => ['min' => 0, 'max' => 20000, 'step' => 1]],
        'body_type' => ['label' => 'Body type', 'type' => 'select', 'options' => [
            'Saloon', 'Hatchback', 'Station wagon', 'Coupe or sports', 'Convertible', 'SUV', 'Pickup', 'Van', 'MPV',
        ]],
        'trim_edition' => ['label' => 'Trim or edition', 'type' => 'text', 'rules' => ['maxlength' => 60]],
        'bicycle_type' => ['label' => 'Type', 'type' => 'select', 'options' => ['Road', 'Mountain', 'Hybrid', 'Kids', 'BMX', 'Electric']],
        'frame_size' => ['label' => 'Frame size', 'type' => 'text', 'rules' => ['maxlength' => 20]],

        // ---- Property ---------------------------------------------------------------------------
        'land_size' => ['label' => 'Land size', 'type' => 'number', 'dimension' => 'area', 'rules' => ['max' => 100000000]],
        'floor_area' => ['label' => 'Floor area', 'type' => 'number', 'dimension' => 'area', 'rules' => ['max' => 1000000, 'units' => ['sqft', 'sqm']]],
        'bedrooms' => ['label' => 'Bedrooms', 'type' => 'select', 'options' => $counts],
        'bathrooms' => ['label' => 'Bathrooms', 'type' => 'select', 'options' => $counts],
        'floors' => ['label' => 'Floors', 'type' => 'number', 'rules' => ['min' => 1, 'max' => 200, 'step' => 1]],
        'furnishing' => ['label' => 'Furnishing', 'type' => 'select', 'options' => ['Unfurnished', 'Semi-furnished', 'Fully furnished']],
        'parking' => ['label' => 'Parking', 'type' => 'boolean'],
        'land_type' => ['label' => 'Land type', 'type' => 'multiselect', 'options' => [
            'Residential', 'Commercial', 'Agricultural',
            ['Tea', 'scope' => $cropCountries], ['Rubber', 'scope' => $cropCountries], ['Coconut', 'scope' => $cropCountries], ['Paddy', 'scope' => $cropCountries],
        ]],
        'deed_type' => ['label' => 'Deed type', 'type' => 'select', 'options' => [
            'Sinnakkara (freehold)', 'Bimsaviya', 'Jayabhoomi', 'Swarnabhoomi', 'Permit', 'Other deed',
        ]],
        'tenure' => ['label' => 'Tenure', 'type' => 'select', 'options' => ['Freehold', 'Leasehold', 'Other tenure']],
        'clear_deed' => ['label' => 'Clear deed', 'type' => 'boolean'],
        'property_type' => ['label' => 'Property type', 'type' => 'select', 'options' => ['Shop', 'Office', 'Warehouse', 'Building', 'Other property']],
        'address_hint' => ['label' => 'Street or area', 'type' => 'text', 'rules' => ['maxlength' => 120], 'help' => 'Optional. Never required to give a full address.'],

        // ---- Vehicle parts ----------------------------------------------------------------------
        'part_category' => ['label' => 'Part category', 'type' => 'select', 'options' => [
            'Engine', 'Body', 'Electrical', 'Suspension', 'Brakes', 'Interior', 'Lights', 'Other part',
        ]],
        'compatible_models' => ['label' => 'Compatible models', 'type' => 'text', 'rules' => ['maxlength' => 120]],
        'part_origin' => ['label' => 'Genuine or aftermarket', 'type' => 'select', 'options' => ['Genuine', 'Aftermarket']],
        'tyre_size' => ['label' => 'Tyre size', 'type' => 'text', 'rules' => ['maxlength' => 20], 'help' => 'For example 185/65R15.'],

        // ---- Phones, tablets and wearables ------------------------------------------------------
        'phone_brand' => ['label' => 'Brand', 'type' => 'select', 'other' => true, 'options' => [
            'Apple', 'Samsung', 'Xiaomi (Redmi, POCO)', 'Huawei', 'Oppo', 'Vivo', 'Realme', 'Nokia', 'Google', 'OnePlus', 'Infinix', 'Tecno',
        ]],
        'phone_model' => ['label' => 'Model', 'type' => 'select', 'other' => true, 'depends_on' => 'phone_brand', 'options' => [
            'apple' => [
                'iPhone 11', 'iPhone 12', 'iPhone 12 Pro', 'iPhone 13', 'iPhone 13 Pro', 'iPhone 13 Pro Max', 'iPhone 14', 'iPhone 14 Plus',
                'iPhone 14 Pro', 'iPhone 14 Pro Max', 'iPhone 15', 'iPhone 15 Plus', 'iPhone 15 Pro', 'iPhone 15 Pro Max', 'iPhone 16',
                'iPhone 16 Plus', 'iPhone 16 Pro', 'iPhone 16 Pro Max', 'iPhone 16e', 'iPhone 17', 'iPhone Air', 'iPhone 17 Pro',
                'iPhone 17 Pro Max', 'iPhone SE', 'iPad', 'iPad Air', 'iPad mini', 'iPad Pro', 'Apple Watch', 'Apple Watch Ultra',
            ],
            'samsung' => [
                'Galaxy A15', 'Galaxy A16', 'Galaxy A25', 'Galaxy A26', 'Galaxy A35', 'Galaxy A36', 'Galaxy A55', 'Galaxy A56',
                'Galaxy S21', 'Galaxy S22', 'Galaxy S23', 'Galaxy S23 FE', 'Galaxy S24', 'Galaxy S24 FE', 'Galaxy S24 Ultra',
                'Galaxy S25', 'Galaxy S25 Ultra', 'Galaxy Z Flip', 'Galaxy Z Fold', 'Galaxy Tab A', 'Galaxy Tab S9', 'Galaxy Watch',
            ],
            'xiaomi-redmi-poco' => [
                'Redmi A3', 'Redmi 13', 'Redmi 14C', 'Redmi Note 13', 'Redmi Note 14', 'POCO M6', 'POCO X6', 'POCO X7', 'POCO F6',
                'Xiaomi 13', 'Xiaomi 13T', 'Xiaomi 14', 'Xiaomi 14T', 'Xiaomi 15', 'Xiaomi Pad 6',
            ],
            'huawei' => ['Nova 11', 'Nova 12', 'P60', 'Pura 70', 'Mate 60', 'MatePad'],
            'oppo' => ['A18', 'A38', 'A58', 'A60', 'A78', 'Reno 10', 'Reno 11', 'Reno 12', 'Reno 13', 'Find X8'],
            'vivo' => ['Y17s', 'Y28', 'Y36', 'V29', 'V30', 'V40', 'V50'],
            'realme' => ['C51', 'C53', 'C55', 'C61', 'C65', 'Realme 11', 'Realme 12', 'Realme 13'],
            'nokia' => ['105', '106', '110', 'C12', 'C21 Plus', 'C32', 'G21', 'G42'],
            'google' => ['Pixel 7', 'Pixel 7a', 'Pixel 8', 'Pixel 8a', 'Pixel 8 Pro', 'Pixel 9', 'Pixel 9a', 'Pixel 9 Pro', 'Pixel 10', 'Pixel 10 Pro'],
            'oneplus' => ['Nord CE 3', 'Nord CE 4', 'Nord 3', 'Nord 4', 'OnePlus 11', 'OnePlus 12', 'OnePlus 13'],
            'infinix' => ['Smart 8', 'Hot 30', 'Hot 40', 'Hot 50', 'Note 30', 'Note 40', 'Zero 30'],
            'tecno' => ['Pop 8', 'Spark 10', 'Spark 20', 'Spark 30', 'Camon 20', 'Camon 30', 'Pova 5', 'Pova 6'],
        ]],
        'storage' => ['label' => 'Storage', 'type' => 'select', 'options' => ['16 GB', '32 GB', '64 GB', '128 GB', '256 GB', '512 GB', '1 TB', '2 TB']],
        'ram' => ['label' => 'RAM', 'type' => 'select', 'options' => ['2 GB', '3 GB', '4 GB', '6 GB', '8 GB', '12 GB', '16 GB', '32 GB', '64 GB']],
        'network_sim' => ['label' => 'SIM', 'type' => 'select', 'options' => ['Single SIM', 'Dual SIM', 'eSIM']],
        'box_included' => ['label' => 'Box and accessories included', 'type' => 'boolean'],

        // ---- Electronics ------------------------------------------------------------------------
        'processor' => ['label' => 'Processor', 'type' => 'select', 'other' => true, 'options' => [
            'Intel Core i3', 'Intel Core i5', 'Intel Core i7', 'Intel Core i9', 'Intel Core Ultra', 'AMD Ryzen 3', 'AMD Ryzen 5',
            'AMD Ryzen 7', 'AMD Ryzen 9', 'Apple M1', 'Apple M2', 'Apple M3', 'Apple M4',
        ]],
        'storage_type' => ['label' => 'Storage type', 'type' => 'select', 'options' => ['SSD', 'HDD', 'SSD and HDD']],
        'graphics' => ['label' => 'Graphics', 'type' => 'text', 'rules' => ['maxlength' => 60]],
        'screen_size' => ['label' => 'Screen size', 'type' => 'number', 'unit' => 'in', 'rules' => ['min' => 1, 'max' => 120, 'step' => 0.1]],
        'tv_type' => ['label' => 'TV type', 'type' => 'select', 'options' => ['LED', 'OLED', 'QLED', 'Smart TV']],

        // ---- Home -------------------------------------------------------------------------------
        'furniture_type' => ['label' => 'Item type', 'type' => 'select', 'options' => ['Sofa', 'Bed', 'Table', 'Chair', 'Wardrobe', 'Cabinet', 'Other furniture']],
        'material' => ['label' => 'Material', 'type' => 'select', 'options' => ['Wood', 'Metal', 'Plastic', 'Glass', 'Fabric', 'Other material']],
        'appliance_type' => ['label' => 'Appliance type', 'type' => 'select', 'options' => [
            'Refrigerator', 'Washing machine', 'Air conditioner', 'Fan', 'Microwave', 'Other appliance',
        ]],
        'capacity' => ['label' => 'Capacity', 'type' => 'text', 'rules' => ['maxlength' => 40], 'help' => 'For example 300 L, 8 kg or 12,000 BTU.'],

        // ---- Other items ------------------------------------------------------------------------
        'gender' => ['label' => 'For', 'type' => 'select', 'options' => ['Men', 'Women', 'Unisex', 'Kids']],
        'size' => ['label' => 'Size', 'type' => 'text', 'rules' => ['maxlength' => 20]],
    ],

    'links' => [
        // Vehicles
        'cars' => $car,
        'vans' => $commercialVehicle,
        'suvs-jeeps' => $car,
        'motorcycles-scooters' => $motorcycle,
        'three-wheelers' => $threeWheeler,
        'lorries-trucks' => $commercialVehicle,
        'buses' => $commercialVehicle,
        'heavy-machinery-tractors' => [
            ['machinery_make', 'required', 'card', 'filter'], ['model_name', 'card'], ['year_of_manufacture', 'card', 'filter'], ['hours_used', 'card', 'filter'],
        ],
        'bicycles' => [['brand', 'filter'], ['bicycle_type', 'card', 'filter'], ['frame_size']],
        'boats' => [['brand', 'filter'], ['model_name'], ['year_of_manufacture', 'filter'], ['engine_capacity']],

        // Property
        'land' => [['land_size', 'required', 'card', 'filter'], ['land_type', 'filter'], ...$deed, ['address_hint']],
        'houses' => [
            ['bedrooms', 'required', 'card', 'filter'], ['bathrooms', 'card', 'filter'], ['land_size', 'card', 'filter'], ['floor_area', 'card', 'filter'],
            ['floors'], ['furnishing', 'filter'], ['parking', 'filter'], ...$deed, ['address_hint'],
        ],
        'apartments' => [
            ['bedrooms', 'required', 'card', 'filter'], ['bathrooms', 'card', 'filter'], ['floor_area', 'card', 'filter'],
            ['furnishing', 'filter'], ['parking', 'filter'], ['address_hint'],
        ],
        'commercial-property' => [
            ['property_type', 'required', 'card', 'filter'], ['land_size', 'card', 'filter'], ['floor_area', 'card', 'filter'],
            ['floors'], ['parking', 'filter'], ['address_hint'],
        ],
        'rooms-annexes' => [['bedrooms', 'required', 'card', 'filter'], ['furnishing', 'filter'], ['address_hint']],
        'holiday-short-stay-rentals' => [['bedrooms', 'card', 'filter'], ['bathrooms', 'filter'], ['furnishing'], ['parking', 'filter'], ['address_hint']],

        // Vehicle parts
        'car-parts' => $parts,
        'motorcycle-parts' => [['part_category', 'required', 'card', 'filter'], ['motorcycle_make', 'filter'], ['compatible_models'], ['brand', 'filter'], ['part_origin', 'card', 'filter']],
        'three-wheeler-parts' => [['part_category', 'required', 'card', 'filter'], ['three_wheeler_make', 'filter'], ['compatible_models'], ['brand', 'filter'], ['part_origin', 'card', 'filter']],
        'tyres-rims' => [['tyre_size', 'card', 'filter'], ['brand', 'card', 'filter'], ['part_origin', 'filter']],
        'car-audio-electronics' => $branded,
        'vehicle-accessories' => $brandOnly,

        // Phones
        'mobile-phones' => $phone,
        'tablets' => $phone,
        'phone-tablet-accessories' => [['phone_brand', 'filter'], ['model_name']],
        'smart-watches-wearables' => $wearable,

        // Electronics
        'laptops' => [...$computer, ['screen_size', 'card', 'filter']],
        'desktop-computers' => $computer,
        'computer-accessories-parts' => $branded,
        'tvs' => [['brand', 'card', 'filter'], ['model_name'], ['screen_size', 'card', 'filter'], ['tv_type', 'filter'], ['warranty', 'filter']],
        'audio-speakers' => $branded,
        'cameras-drones' => $branded,
        'video-games-consoles' => $branded,
        'other-electronics' => $branded,

        // Home
        'furniture' => [['furniture_type', 'card', 'filter'], ['material', 'filter']],
        'home-appliances' => [['appliance_type', 'card', 'filter'], ['brand', 'card', 'filter'], ['capacity'], ['warranty', 'filter']],
        'kitchen-items' => $brandOnly,
        'home-decor' => [],
        'garden' => [],

        // Other items
        'fashion-clothing' => [['gender', 'card', 'filter'], ['size', 'card'], ['brand', 'filter']],
        'watches-jewellery' => $brandOnly,
        'sports-fitness' => $brandOnly,
        'books-education' => [],
        'musical-instruments' => $brandOnly,
        'baby-kids' => $brandOnly,
        'health-beauty' => $brandOnly,
        'business-industrial-equipment' => $brandOnly,
        'everything-else' => [],
    ],

    'unit_price' => ['land' => 'land_size'],
];
