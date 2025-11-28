<?php
require_once 'config.php';

// Handle form messages
$message = '';
$messageType = '';
if (isset($_SESSION['form_message'])) {
    $message = $_SESSION['form_message'];
    $messageType = $_SESSION['form_message_type'] ?? 'info';
    unset($_SESSION['form_message'], $_SESSION['form_message_type']);
}

$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_errors']);

// Initialize form data from session if available
$formData = $_SESSION['application_form_data'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accredited TVET Colleges in South Africa</title>
    <link href="https://cdn.jsdelivr.net/npm/lucide-static@0.263.1/font/lucide.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        
        body {
            background-color: #f0f7ff;
            color: #2d3748;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header Styles */
        .header {
            text-align: center;
            padding: 40px 0 30px;
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            border-radius: 0 0 20px 20px;
            margin-bottom: 30px;
        }
        
        .header-icon {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .header-icon i {
            font-size: 32px;
            color: white;
            margin-right: 10px;
        }
        
        h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .header-description {
            font-size: 1.25rem;
            max-width: 800px;
            margin: 0 auto;
            opacity: 0.9;
        }
        
        /* Stats Section */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #3b82f6;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #64748b;
            font-weight: 500;
        }
        
        /* Search and Filter Section */
        .search-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 24px;
            margin-bottom: 32px;
        }
        
        .search-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }
        
        @media (min-width: 768px) {
            .search-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        .search-input, .filter-select {
            position: relative;
        }
        
        .search-input i, .filter-select i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }
        
        input, select {
            width: 100%;
            padding: 12px 16px 12px 40px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.2s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        
        .search-input input {
            padding-left: 40px;
        }
        
        .filter-select select {
            padding-left: 40px;
            appearance: none;
        }
        
        /* Colleges Grid */
        .colleges-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
            margin-bottom: 40px;
        }
        
        @media (min-width: 768px) {
            .colleges-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        .college-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .college-card:hover {
            transform: translateY(-5px);
        }
        
        .college-header {
            padding: 20px;
            background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
            color: white;
        }
        
        .college-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .college-location {
            display: flex;
            align-items: center;
            font-weight: 500;
        }
        
        .college-location i {
            margin-right: 6px;
        }
        
        .college-body {
            padding: 20px;
        }
        
        .college-detail {
            margin-bottom: 15px;
        }
        
        .detail-title {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }
        
        .detail-title i {
            margin-right: 8px;
            color: #3b82f6;
        }
        
        .campuses-list, .programs-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .campus-tag, .program-tag {
            background: #f1f5f9;
            color: #475569;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .program-tag {
            background: #e0f2fe;
            color: #0369a1;
        }
        
        .college-footer {
            padding: 15px 20px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .contact-link {
            display: flex;
            align-items: center;
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
        }
        
        .contact-link i {
            margin-right: 6px;
        }
        
        .contact-link:hover {
            text-decoration: underline;
        }
        
        /* Province Section */
        .province-section {
            margin-bottom: 40px;
        }
        
        .province-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .province-name {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e40af;
        }
        
        .province-college-count {
            background: #3b82f6;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            margin-left: 12px;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            padding: 30px 0;
            color: #64748b;
            font-size: 14px;
            border-top: 1px solid #e2e8f0;
            margin-top: 40px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            grid-column: 1 / -1;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #9ca3af;
            margin-bottom: 16px;
        }
        
        .empty-state p {
            color: #6b7280;
            margin-bottom: 8px;
        }
        
        .empty-state p:first-of-type {
            font-size: 18px;
        }
        
        /* Province Colors */
        .western-cape { border-left: 5px solid #10b981; }
        .gauteng { border-left: 5px solid #f59e0b; }
        .kwaZulu-natal { border-left: 5px solid #ef4444; }
        .eastern-cape { border-left: 5px solid #8b5cf6; }
        .limpopo { border-left: 5px solid #ec4899; }
        .mpumalanga { border-left: 5px solid #14b8a6; }
        .north-west { border-left: 5px solid #f97316; }
        .free-state { border-left: 5px solid #06b6d4; }
        .northern-cape { border-left: 5px solid #a855f7; }
    </style>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>
    
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-icon">
                <i class="icon-graduation-cap"></i>
            </div>
            <h1>Accredited TVET Colleges in South Africa</h1>
            <p class="header-description">
                Explore all accredited Technical and Vocational Education and Training colleges across all nine provinces of South Africa
            </p>
        </div>

        <!-- Stats Section -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number">50</div>
                <div class="stat-label">TVET Colleges</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">9</div>
                <div class="stat-label">Provinces</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">300+</div>
                <div class="stat-label">Campuses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">100+</div>
                <div class="stat-label">Programs</div>
            </div>
        </div>

        <!-- Search and Filter Controls -->
        <div class="search-section">
            <div class="search-grid">
                <div class="search-input">
                    <i class="icon-search"></i>
                    <input type="text" id="search-input" placeholder="Search colleges or programs...">
                </div>
                
                <div class="filter-select">
                    <i class="icon-filter"></i>
                    <select id="province-filter">
                        <option value="all">All Provinces</option>
                        <option value="Eastern Cape">Eastern Cape</option>
                        <option value="Free State">Free State</option>
                        <option value="Gauteng">Gauteng</option>
                        <option value="KwaZulu-Natal">KwaZulu-Natal</option>
                        <option value="Limpopo">Limpopo</option>
                        <option value="Mpumalanga">Mpumalanga</option>
                        <option value="North West">North West</option>
                        <option value="Northern Cape">Northern Cape</option>
                        <option value="Western Cape">Western Cape</option>
                    </select>
                </div>
                
                <select id="program-filter">
                    <option value="all">All Programs</option>
                    <option value="Engineering">Engineering</option>
                    <option value="Business">Business</option>
                    <option value="Hospitality">Hospitality</option>
                    <option value="IT">Information Technology</option>
                    <option value="Education">Education</option>
                    <option value="Agriculture">Agriculture</option>
                    <option value="Tourism">Tourism</option>
                </select>
                
                <button id="reset-filters" style="padding: 12px; background: #ef4444; color: white; border: none; border-radius: 8px; cursor: pointer;">
                    Reset Filters
                </button>
            </div>
        </div>

        <!-- Colleges Container -->
        <div id="colleges-container">
            <!-- Colleges will be populated by JavaScript -->
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>© 2023 Department of Higher Education and Training - Republic of South Africa</p>
            <p>All TVET colleges listed are accredited by the South African Department of Higher Education and Training</p>
        </div>
    </div>

    <script>
        // TVET college data for all provinces
        const tvetColleges = [
            {
                name: "Orbit TVET College",
                province: "North West",
                campuses: ["Rustenburg", "Mankwe", "Brits", "Mogwase"],
                programs: ["Business Studies", "Engineering Studies", "Utility Studies", "Hospitality"],
                website: "https://www.orbitcollege.co.za",
                phone: "+27 14 592 8714",
                email: "info@orbitcollege.co.za",
                accreditation: "Full"
            },
            {
                name: "Taletso TVET College",
                province: "North West",
                campuses: ["Mmabatho", "Lehurutshe", "Zeerust", "Ramotshere"],
                programs: ["Business Studies", "Engineering Studies", "Education & Development", "Agriculture"],
                website: "https://www.taletso.edu.za",
                phone: "+27 18 384 2100",
                email: "info@taletso.edu.za",
                accreditation: "Full"
            },
            {
                name: "Westcol TVET College",
                province: "Gauteng",
                campuses: ["Randfontein", "Krugersdorp", "Carletonville", "Westonaria"],
                programs: ["Business Studies", "Engineering Studies", "Hospitality", "Information Technology"],
                website: "https://www.westcol.co.za",
                phone: "+27 11 692 4004",
                email: "info@westcol.co.za",
                accreditation: "Full"
            },
            {
                name: "Vuselela TVET College",
                province: "North West",
                campuses: ["Klerksdorp", "Matlosana", "Potchefstroom", "Tlokwe"],
                programs: ["Engineering Studies", "Business Studies", "Utility Studies", "Education & Development"],
                website: "https://www.vuselelacollege.co.za",
                phone: "+27 18 406 7800",
                email: "info@vuselelacollege.co.za",
                accreditation: "Full"
            },
            {
                name: "False Bay TVET College",
                province: "Western Cape",
                campuses: ["Westlake", "Khayelitsha", "Muizenberg", "Fish Hoek", "Mitchells Plain"],
                programs: ["Engineering Studies", "Business Studies", "Education & Development", "Hospitality", "Information Technology"],
                website: "https://www.falsebaycollege.co.za",
                phone: "+27 21 788 8373",
                email: "info@falsebay.org.za",
                accreditation: "Full"
            },
            {
                name: "College of Cape Town",
                province: "Western Cape",
                campuses: ["City", "Crawford", "Guguletu", "Thornton", "Pinelands"],
                programs: ["Art & Design", "Business", "Engineering", "Hospitality", "IT & Computer Science"],
                website: "https://www.cct.edu.za",
                phone: "+27 21 404 6700",
                email: "info@cct.edu.za",
                accreditation: "Full"
            },
            {
                name: "Buffalo City TVET College",
                province: "Eastern Cape",
                campuses: ["East London", "St Marks", "King Street", "John Knox", "Queens Street"],
                programs: ["Business Management", "Finance Economics & Accounting", "Marketing", "Office Administration", "Engineering"],
                website: "https://www.bccollege.co.za",
                phone: "+27 43 704 9200",
                email: "info@bccollege.co.za",
                accreditation: "Full"
            },
            {
                name: "Ingwe TVET College",
                province: "Eastern Cape",
                campuses: ["Ngqungqushe", "Crestway", "Mthatha", "Elliot"],
                programs: ["Business Studies", "Engineering Studies", "Utility Studies", "Hospitality"],
                website: "https://www.ingwecollege.edu.za",
                phone: "+27 47 873 8804",
                email: "info@ingwecollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "King Hintsa TVET College",
                province: "Eastern Cape",
                campuses: ["Willowvale", "Teko", "Cala", "Gcuwa"],
                programs: ["Business Studies", "Engineering Studies", "Utility Studies", "Education & Development"],
                website: "https://www.kinghintsacollege.edu.za",
                phone: "+27 47 401 6400",
                email: "info@kinghintsacollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "King Sabata Dalindyebo TVET College",
                province: "Eastern Cape",
                campuses: ["Mthatha", "Libode", "Ngcobo", "Mqanduli"],
                programs: ["Business Studies", "Engineering Studies", "Utility Studies", "Hospitality"],
                website: "https://www.ksdcollege.edu.za",
                phone: "+27 47 537 0200",
                email: "info@ksdcollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "Lovedale TVET College",
                province: "Eastern Cape",
                campuses: ["Alice", "King William's Town", "Zwelerisha", "East London"],
                programs: ["Business Studies", "Engineering Studies", "Utility Studies", "Information Technology"],
                website: "https://www.lovedalecollege.edu.za",
                phone: "+27 43 604 0600",
                email: "info@lovedalecollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "Port Elizabeth TVET College",
                province: "Eastern Cape",
                campuses: ["Dower", "Iqhayiya", "Kemsley", "Russell Road", "Vista"],
                programs: ["Engineering Studies", "Business Studies", "Utility Studies", "Hospitality", "Tourism"],
                website: "https://www.pecollege.edu.za",
                phone: "+27 41 404 6000",
                email: "info@pecollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "Flavius Mareka TVET College",
                province: "Free State",
                campuses: ["Sasolburg", "Sedibeng", "Goldsmith", "Glen"],
                programs: ["Engineering Studies", "Business Studies", "Utility Studies", "Hospitality"],
                website: "https://www.fmcollege.co.za",
                phone: "+27 16 976 0441",
                email: "info@fmcollege.co.za",
                accreditation: "Full"
            },
            {
                name: "Goldfields TVET College",
                province: "Free State",
                campuses: ["Welkom", "Tosa", "Odendaalsrus", "Kroonstad"],
                programs: ["Business Studies", "Engineering Studies", "Utility Studies", "Information Technology"],
                website: "https://www.goldfieldscollege.edu.za",
                phone: "+27 57 910 6000",
                email: "info@goldfieldscollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "Maluti TVET College",
                province: "Free State",
                campuses: ["Phuthaditjhaba", "Mapetla", "Matsieng", "Qwaqwa"],
                programs: ["Business Studies", "Engineering Studies", "Utility Studies", "Education & Development"],
                website: "https://www.maluticollege.edu.za",
                phone: "+27 58 713 0300",
                email: "info@maluticollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "Motheo TVET College",
                province: "Free State",
                campuses: ["Bloemfontein", "Thaba Nchu", "Botshabelo", "Harrismith"],
                programs: ["Engineering Studies", "Business Studies", "Utility Studies", "Hospitality"],
                website: "https://www.motheocollege.edu.za",
                phone: "+27 51 406 9000",
                email: "info@motheocollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "Central Johannesburg TVET College",
                province: "Gauteng",
                campuses: ["Ellis Park", "Langlaagte", "Auckland Park", "Kdo", "Soweto"],
                programs: ["Engineering", "Business Studies", "Utility Studies", "Safety in Society", "Education & Development"],
                website: "https://www.cjc.edu.za",
                phone: "+27 11 351 6000",
                email: "info@cjc.edu.za",
                accreditation: "Full"
            },
            {
                name: "Ekurhuleni East TVET College",
                province: "Gauteng",
                campuses: ["Kwa-Thema", "Springs", "Daveyton", "Benoni", "Brakpan"],
                programs: ["Business Studies", "Engineering Studies", "Utility Studies", "Hospitality"],
                website: "https://www.eec.edu.za",
                phone: "+27 11 736 4400",
                email: "info@eec.edu.za",
                accreditation: "Full"
            },
            {
                name: "Ekurhuleni West TVET College",
                province: "Gauteng",
                campuses: ["Germiston", "Alberton", "Kathorus", "Boksburg", "Edenvale"],
                programs: ["Engineering Studies", "Business Studies", "Utility Studies", "Information Technology"],
                website: "https://www.ewc.edu.za",
                phone: "+27 11 323 1600",
                email: "info@ewc.edu.za",
                accreditation: "Full"
            },
            {
                name: "Sedibeng TVET College",
                province: "Gauteng",
                campuses: ["Vereeniging", "Heidelberg", "Sebokeng", "Vanderbijlpark"],
                programs: ["Business Studies", "Engineering Studies", "Utility Studies", "Education & Development"],
                website: "https://www.sedibengcollege.edu.za",
                phone: "+27 16 422 3000",
                email: "info@sedibengcollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "South West Gauteng TVET College",
                province: "Gauteng",
                campuses: ["Roodepoort", "Moletsane", "George Tabor", "Dobsonville", "Technisa"],
                programs: ["Engineering Studies", "Business Studies", "Utility Studies", "Hospitality"],
                website: "https://www.swgc.co.za",
                phone: "+27 11 527 1000",
                email: "info@swgc.co.za",
                accreditation: "Full"
            },
            {
                name: "Tshwane North TVET College",
                province: "Gauteng",
                campuses: ["Pretoria", "Mamelodi", "Rosslyn", "Soshanguve", "Temba"],
                programs: ["Engineering Studies", "Business Studies", "Utility Studies", "Information Technology"],
                website: "https://www.tnc.edu.za",
                phone: "+27 12 401 5000",
                email: "info@tnc.edu.za",
                accreditation: "Full"
            },
            {
                name: "Tshwane South TVET College",
                province: "Gauteng",
                campuses: ["Atteridgeville", "Centurion", "Odi", "Pretoria West", "Temba"],
                programs: ["Civil Engineering", "Electrical Engineering", "Mechanical Engineering", "Business Management", "Finance Economics & Accounting"],
                website: "https://www.tsc.edu.za",
                phone: "+27 12 401 5000",
                email: "info@tsc.edu.za",
                accreditation: "Full"
            },
            {
                name: "Coastal TVET College",
                province: "KwaZulu-Natal",
                campuses: ["Umlazi", "Amanzimtoti", "Isipingo", "Kwamakhutha", "Umbumbulu"],
                programs: ["Business Studies", "Engineering Studies", "Utility Studies", "Hospitality"],
                website: "https://www.coastalcollege.edu.za",
                phone: "+27 31 905 7000",
                email: "info@coastalcollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "Elangeni TVET College",
                province: "KwaZulu-Natal",
                campuses: ["Durban", "Pinetown", "KwaDabeka", "Inchanga", "Qadi"],
                programs: ["Engineering Studies", "Business Studies", "Utility Studies", "Information Technology"],
                website: "https://www.elangenedcol.co.za",
                phone: "+27 31 716 6700",
                email: "info@elangenedcol.co.za",
                accreditation: "Full"
            },
            {
                name: "Majuba TVET College",
                province: "KwaZulu-Natal",
                campuses: ["Newcastle", "Dundee", "Madadeni", "Osizweni", "Volksrust"],
                programs: ["Engineering Studies", "Business Studies", "Skills Programs", "Occupational Programs", "Agricultural Studies"],
                website: "https://www.majuba.edu.za",
                phone: "+27 34 326 4888",
                email: "info@majuba.edu.za",
                accreditation: "Full"
            },
            {
                name: "Mnambithi TVET College",
                province: "KwaZulu-Natal",
                campuses: ["Ladysmith", "Ezakheni", "Hlobane", "Dundee"],
                programs: ["Business Studies", "Engineering Studies", "Utility Studies", "Education & Development"],
                website: "https://www.mnambithicollege.edu.za",
                phone: "+27 36 631 0360",
                email: "info@mnambithicollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "Mthashana TVET College",
                province: "KwaZulu-Natal",
                campuses: ["Vryheid", "KwaGqikazi", "Nongoma", "Pongola"],
                programs: ["Engineering Studies", "Business Studies", "Utility Studies", "Hospitality"],
                website: "https://www.mthashanacollege.edu.za",
                phone: "+27 34 980 1000",
                email: "info@mthashanacollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "Thekwini TVET College",
                province: "KwaZulu-Natal",
                campuses: ["Asherville", "Berea", "Cato Manor", "Stamford Hill", "Springfield"],
                programs: ["Engineering Studies", "Business Studies", "Utility Studies", "Information Technology"],
                website: "https://www.thekwinicollege.edu.za",
                phone: "+27 31 250 8400",
                email: "info@thekwinicollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "Umfolozi TVET College",
                province: "KwaZulu-Natal",
                campuses: ["Richards Bay", "Eshowe", "Mandlazini", "KwaMbonambi"],
                programs: ["Business Studies", "Engineering Studies", "Utility Studies", "Hospitality"],
                website: "https://www.umfolozicollege.edu.za",
                phone: "+27 35 902 9501",
                email: "info@umfolozicollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "Umgungundlovu TVET College",
                province: "KwaZulu-Natal",
                campuses: ["Pietermaritzburg", "Northdale", "Plessislaer", "Sobantu"],
                programs: ["Engineering Studies", "Business Studies", "Utility Studies", "Education & Development"],
                website: "https://www.umgungundlovucollege.edu.za",
                phone: "+27 33 341 2201",
                email: "info@umgungundlovucollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "Capricorn TVET College",
                province: "Limpopo",
                campuses: ["Polokwane", "Seshego", "Senwabarwana", "Gaba"],
                programs: ["Business Studies", "Engineering Studies", "Utility Studies", "Information Technology"],
                website: "https://www.capricorncollege.edu.za",
                phone: "+27 15 230 1800",
                email: "info@capricorncollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "Lephalale TVET College",
                province: "Limpopo",
                campuses: ["Lephalale", "Ellisras", "Onverwacht", "Mokopane"],
                programs: ["Engineering Studies", "Business Studies", "Utility Studies", "Hospitality"],
                website: "https://www.lephalalecollege.edu.za",
                phone: "+27 14 763 2252",
                email: "info@lephalalecollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "Letaba TVET College",
                province: "Limpopo",
                campuses: ["Tzaneen", "Maake", "Giyani", "Phalaborwa"],
                programs: ["Business Studies", "Engineering Studies", "Utility Studies", "Education & Development"],
                website: "https://www.letabacollege.edu.za",
                phone: "+27 15 307 5440",
                email: "info@letabacollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "Mopani South East TVET College",
                province: "Limpopo",
                campuses: ["Phalaborwa", "Namakgale", "Burgersfort", "Tubatse"],
                programs: ["Engineering Studies", "Business Studies", "Utility Studies", "Information Technology"],
                website: "https://www.mopanicollege.edu.za",
                phone: "+27 15 781 5721",
                email: "info@mopanicollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "Sekhukhune TVET College",
                province: "Limpopo",
                campuses: ["Motetema", "Apel", "Central Office", "Marble Hall"],
                programs: ["Business Studies", "Engineering Studies", "Utility Studies", "Hospitality"],
                website: "https://www.sekhukhunecollege.edu.za",
                phone: "+27 13 269 0028",
                email: "info@sekhukhunecollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "Vhembe TVET College",
                province: "Limpopo",
                campuses: ["Sibasa", "Makwarela", "Makhado", "Tshilamba"],
                programs: ["Engineering Studies", "Business Studies", "Utility Studies", "Education & Development"],
                website: "https://www.vhembecollege.edu.za",
                phone: "+27 15 963 7000",
                email: "info@vhembecollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "Waterberg TVET College",
                province: "Limpopo",
                campuses: ["Mokopane", "Thabazimbi", "Modimolle", "Bela-Bela"],
                programs: ["Business Studies", "Engineering Studies", "Utility Studies", "Information Technology"],
                website: "https://www.waterbergcollege.edu.za",
                phone: "+27 14 718 3000",
                email: "info@waterbergcollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "Ehlanzeni TVET College",
                province: "Mpumalanga",
                campuses: ["Nelspruit", "Mapulaneng", "Mlumati", "Malekutu", "Matsulu"],
                programs: ["Engineering Studies", "Business Studies", "Utility Studies", "Hospitality"],
                website: "https://www.ehlanzenicollege.edu.za",
                phone: "+27 13 752 7105",
                email: "info@ehlanzenicollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "Gert Sibande TVET College",
                province: "Mpumalanga",
                campuses: ["Ermelo", "Standerton", "Evander", "Balfour", "Perdebank"],
                programs: ["Business Studies", "Engineering Studies", "Utility Studies", "Information Technology"],
                website: "https://www.gscollege.edu.za",
                phone: "+27 17 718 3000",
                email: "info@gscollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "Nkangala TVET College",
                province: "Mpumalanga",
                campuses: ["Witbank", "Middleburg", "Vandyksdrif", "Vaalbank", "Vergenoeg"],
                programs: ["Engineering Studies", "Business Studies", "Utility Studies", "Education & Development"],
                website: "https://www.nkangalacollege.edu.za",
                phone: "+27 13 690 4000",
                email: "info@nkangalacollege.edu.za",
                accreditation: "Full"
            },
            {
                name: "Northern Cape Urban TVET College",
                province: "Northern Cape",
                campuses: ["Kimberley", "Diamantveld", "Pixley Ka Seme", "Dithakong"],
                programs: ["Business Studies", "Engineering Studies", "Utility Studies", "Hospitality"],
                website: "https://www.ncutvet.edu.za",
                phone: "+27 53 839 2000",
                email: "info@ncutvet.edu.za",
                accreditation: "Full"
            },
            {
                name: "Northern Cape Rural TVET College",
                province: "Northern Cape",
                campuses: ["De Aar", "Upington", "Kathu", "Springbok", "Kuruman"],
                programs: ["Engineering Studies", "Business Studies", "Utility Studies", "Information Technology"],
                website: "https://www.ncrvet.edu.za",
                phone: "+27 53 723 0100",
                email: "info@ncrvet.edu.za",
                accreditation: "Full"
            }
        ];

        // DOM elements
        const searchInput = document.getElementById('search-input');
        const provinceFilter = document.getElementById('province-filter');
        const programFilter = document.getElementById('program-filter');
        const resetFiltersBtn = document.getElementById('reset-filters');
        const collegesContainer = document.getElementById('colleges-container');

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            renderCollegesByProvince(tvetColleges);
            
            // Add event listeners
            searchInput.addEventListener('input', filterColleges);
            provinceFilter.addEventListener('change', filterColleges);
            programFilter.addEventListener('change', filterColleges);
            resetFiltersBtn.addEventListener('click', resetFilters);
        });

        // Render colleges grouped by province
        function renderCollegesByProvince(colleges) {
            collegesContainer.innerHTML = '';
            
            // Group colleges by province
            const collegesByProvince = {};
            colleges.forEach(college => {
                if (!collegesByProvince[college.province]) {
                    collegesByProvince[college.province] = [];
                }
                collegesByProvince[college.province].push(college);
            });
            
            // Render each province section
            for (const province in collegesByProvince) {
                const provinceColleges = collegesByProvince[province];
                
                const provinceSection = document.createElement('div');
                provinceSection.className = 'province-section';
                
                const provinceHeader = document.createElement('div');
                provinceHeader.className = 'province-header';
                provinceHeader.innerHTML = `
                    <h2 class="province-name">${province}</h2>
                    <span class="province-college-count">${provinceColleges.length} colleges</span>
                `;
                
                provinceSection.appendChild(provinceHeader);
                
                const collegesGrid = document.createElement('div');
                collegesGrid.className = 'colleges-grid';
                
                provinceColleges.forEach(college => {
                    const collegeCard = createCollegeCard(college);
                    collegesGrid.appendChild(collegeCard);
                });
                
                provinceSection.appendChild(collegesGrid);
                collegesContainer.appendChild(provinceSection);
            }
        }

        // Filter colleges based on search and filters
        function filterColleges() {
            const searchTerm = searchInput.value.trim().toLowerCase();
            const provinceValue = provinceFilter.value;
            const programValue = programFilter.value;

            const filteredColleges = tvetColleges.filter(college => {
                // Search term filter
                if (searchTerm && 
                    !college.name.toLowerCase().includes(searchTerm) &&
                    !college.province.toLowerCase().includes(searchTerm) &&
                    !college.campuses.some(campus => campus.toLowerCase().includes(searchTerm)) &&
                    !college.programs.some(program => program.toLowerCase().includes(searchTerm))) {
                    return false;
                }

                // Province filter
                if (provinceValue !== 'all' && college.province !== provinceValue) {
                    return false;
                }

                // Program filter
                if (programValue !== 'all' && !college.programs.some(p => p.toLowerCase().includes(programValue.toLowerCase()))) {
                    return false;
                }

                return true;
            });

            renderCollegesByProvince(filteredColleges);
        }

        // Reset all filters
        function resetFilters() {
            searchInput.value = '';
            provinceFilter.value = 'all';
            programFilter.value = 'all';
            renderCollegesByProvince(tvetColleges);
        }

        // Create college card HTML
        function createCollegeCard(college) {
            const card = document.createElement('div');
            card.className = `college-card ${college.province.toLowerCase().replace(/\s+/g, '-')}`;
            
            card.innerHTML = `
                <div class="college-header">
                    <h2 class="college-name">${college.name}</h2>
                    <div class="college-location">
                        <i class="icon-map-pin"></i>
                        <span>${college.province}</span>
                    </div>
                </div>
                
                <div class="college-body">
                    <div class="college-detail">
                        <div class="detail-title">
                            <i class="icon-home"></i>
                            <span>Campuses</span>
                        </div>
                        <div class="campuses-list">
                            ${college.campuses.map(campus => `<span class="campus-tag">${campus}</span>`).join('')}
                        </div>
                    </div>
                    
                    <div class="college-detail">
                        <div class="detail-title">
                            <i class="icon-book-open"></i>
                            <span>Programs</span>
                        </div>
                        <div class="programs-list">
                            ${college.programs.slice(0, 4).map(program => `<span class="program-tag">${program}</span>`).join('')}
                            ${college.programs.length > 4 ? `<span class="program-tag">+${college.programs.length - 4} more</span>` : ''}
                        </div>
                    </div>
                    
                    <div class="college-detail">
                        <div class="detail-title">
                            <i class="icon-award"></i>
                            <span>Accreditation: ${college.accreditation}</span>
                        </div>
                    </div>
                </div>
                
                <div class="college-footer">
                    <a href="tel:${college.phone}" class="contact-link">
                        <i class="icon-phone"></i>
                        <span>Call</span>
                    </a>
                    <a href="mailto:${college.email}" class="contact-link">
                        <i class="icon-mail"></i>
                        <span>Email</span>
                    </a>
                    <a href="${college.website}" target="_blank" class="contact-link">
                        <i class="icon-external-link"></i>
                        <span>Website</span>
                    </a>
                </div>
            `;
            
            return card;
        }
    </script>
</body>
</html>