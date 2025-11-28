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
    <title>South African Universities - EduBridgeSA</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        :root {
            --primary: #1a5fb4;
            --primary-dark: #0f4a8f;
            --secondary: #ff7b00;
            --accent: #00b894;
        }
        
        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #1a237e;
        }

        .logo img {
            height: 50px;
            margin-right: 1rem;
        }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
        
        .gradient-blue {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 50%, #1e40af 100%);
        }
        
        .gradient-green {
            background: linear-gradient(to right, var(--accent), #16a34a);
        }
        
        .hover-lift:hover {
            transform: translateY(-2px);
            transition: transform 0.2s ease-in-out;
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .university-card {
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary);
        }
        
        .university-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-weight: bold;
            font-size: 0.875rem;
        }
        
        .rank-1 { background-color: #fde68a; color: #92400e; }
        .rank-2 { background-color: #e5e7eb; color: #374151; }
        .rank-3 { background-color: #fbcfe8; color: #9d174d; }
        .rank-top { background-color: #dbeafe; color: #1e40af; }
        .rank-good { background-color: #d1fae5; color: #065f46; }
        .rank-other { background-color: #f3f4f6; color: #4b5563; }
        
        /* Login/Logout buttons */
        .login-btn {
            color: #1e40af !important;
            text-decoration: none;
            padding: 8px 16px;
            border: 2px solid #1e40af;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .login-btn:hover {
            background: #1e40af;
            color: white !important;
        }

        .btn-signup {
            background: #f59e0b !important;
            color: white !important;
            font-size: 0.9rem;
            padding: 8px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
        }

        .logout-btn {
            color: #dc2626 !important;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .logout-btn:hover {
            background: #dc2626;
            color: white !important;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php 
    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    include 'includes/navigation.php'; 
    ?>

    <!-- Universities Content -->
    <div class="min-h-screen bg-gray-50 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="flex items-center justify-center mb-4">
                    <i data-lucide="school" class="h-8 w-8 text-blue-600 mr-2"></i>
                    <h1 class="text-4xl font-bold text-gray-900">South African Universities</h1>
                </div>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Explore all 26 public universities and over 50 leading private institutions in South Africa with application dates and requirements.
                </p>
            </div>

            <!-- Filters and Search -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Institution Type</label>
                        <select id="typeFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="all">All Institutions</option>
                            <option value="public">Public Universities</option>
                            <option value="private">Private Institutions</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">QS Ranking</label>
                        <select id="rankingFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="all">All Rankings</option>
                            <option value="top500">Top 500 Worldwide</option>
                            <option value="top1000">Top 1000 Worldwide</option>
                            <option value="unranked">Not Ranked</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Province</label>
                        <select id="provinceFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="all">All Provinces</option>
                            <option value="gauteng">Gauteng</option>
                            <option value="western-cape">Western Cape</option>
                            <option value="eastern-cape">Eastern Cape</option>
                            <option value="kwazulu-natal">KwaZulu-Natal</option>
                            <option value="other">Other Provinces</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <input 
                            type="text" 
                            id="searchInput" 
                            placeholder="Search universities..." 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                    </div>
                </div>
            </div>

            <!-- Application Dates Alert -->
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-8 rounded-md">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i data-lucide="info" class="h-5 w-5 text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>Application Note:</strong> Dates shown are for 2025 intake. Always verify with individual institutions as dates may change.
                            Most public universities close applications between June-September 2025.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Universities Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="universitiesContainer">
                <!-- Universities will be populated by JavaScript -->
            </div>

            <!-- No Results Message -->
            <div id="noResults" class="hidden text-center py-12">
                <i data-lucide="search" class="h-12 w-12 text-gray-400 mx-auto mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No universities found</h3>
                <p class="text-gray-600">Try adjusting your filters or search terms</p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <p>&copy; 2025 EduBridgeSA. All rights reserved.</p>
                <p class="text-gray-400 mt-2">Comprehensive South African university database</p>
            </div>
        </div>
    </footer>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // University database
        const universities = [
          {
            "name": "University of Cape Town",
            "short": "UCT",
            "website": "https://www.uct.ac.za",
            "qs2026_global_rank": 171,
            "applications": {
              "undergrad_open": "TBA",
              "undergrad_close": "TBA"
            },
            "notes": "Dates typically April-July - confirm on UCT faculty pages",
            "type": "public",
            "province": "western-cape"
          },
          {
            "name": "University of the Witwatersrand",
            "short": "Wits",
            "website": "https://www.wits.ac.za",
            "qs2026_global_rank": 267,
            "applications": {
              "undergrad_open": "2025-03-01",
              "undergrad_close": "2025-09-30",
              "undergrad_close_general": "2025-06-30"
            },
            "notes": "Health Sciences/Architecture/BA Film & TV close 30 June 2025",
            "type": "public",
            "province": "gauteng"
          },
          {
            "name": "Stellenbosch University",
            "short": "SU",
            "website": "https://www.sun.ac.za",
            "qs2026_global_rank": 296,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-07-31"
            },
            "notes": "Financial aid applications: 1-31 October 2025",
            "type": "public",
            "province": "western-cape"
          },
          {
            "name": "University of KwaZulu-Natal",
            "short": "UKZN",
            "website": "https://www.ukzn.ac.za",
            "qs2026_global_rank": 380,
            "applications": {
              "undergrad_open": "TBA",
              "undergrad_close": "TBA"
            },
            "notes": "Dates typically April-September - confirm per programme",
            "type": "public",
            "province": "kwazulu-natal"
          },
          {
            "name": "University of Pretoria",
            "short": "UP",
            "website": "https://www.up.ac.za",
            "qs2026_global_rank": 407,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-06-30"
            },
            "notes": "Veterinary Science closes 31 May 2025",
            "type": "public",
            "province": "gauteng"
          },
          {
            "name": "University of Johannesburg",
            "short": "UJ",
            "website": "https://www.uj.ac.za",
            "qs2026_global_rank": 551,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-10-31"
            },
            "notes": "Applications close at 12:00 on closing date",
            "type": "public",
            "province": "gauteng"
          },
          {
            "name": "University of South Africa",
            "short": "UNISA",
            "website": "https://www.unisa.ac.za",
            "qs2026_global_rank": 801,
            "applications": {
              "undergrad_open": "TBA",
              "undergrad_close": "TBA"
            },
            "notes": "Distance learning cycles - check qualification pages",
            "type": "public",
            "province": "gauteng"
          },
          {
            "name": "Rhodes University",
            "short": "RU",
            "website": "https://www.ru.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-07-31"
            },
            "notes": "Provisional dates - confirm with university",
            "type": "public",
            "province": "eastern-cape"
          },
          {
            "name": "University of the Western Cape",
            "short": "UWC",
            "website": "https://www.uwc.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-07-31"
            },
            "notes": "Provisional dates - confirm with university",
            "type": "public",
            "province": "western-cape"
          },
          {
            "name": "North-West University",
            "short": "NWU",
            "website": "https://www.nwu.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-07-31"
            },
            "notes": "Provisional dates - confirm with university",
            "type": "public",
            "province": "other"
          },
          {
            "name": "University of the Free State",
            "short": "UFS",
            "website": "https://www.ufs.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-07-31"
            },
            "notes": "Provisional dates - confirm with university",
            "type": "public",
            "province": "other"
          },
          {
            "name": "Nelson Mandela University",
            "short": "NMU",
            "website": "https://www.mandela.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-07-31"
            },
            "notes": "Provisional dates - confirm with university",
            "type": "public",
            "province": "eastern-cape"
          },
          {
            "name": "University of Limpopo",
            "short": "UL",
            "website": "https://www.ul.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-07-31"
            },
            "notes": "Provisional dates - confirm with university",
            "type": "public",
            "province": "other"
          },
          {
            "name": "University of Zululand",
            "short": "UNIZULU",
            "website": "https://www.unizulu.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-07-31"
            },
            "notes": "Provisional dates - confirm with university",
            "type": "public",
            "province": "kwazulu-natal"
          },
          {
            "name": "Walter Sisulu University",
            "short": "WSU",
            "website": "https://www.wsu.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-07-31"
            },
            "notes": "Provisional dates - confirm with university",
            "type": "public",
            "province": "eastern-cape"
          },
          {
            "name": "University of Venda",
            "short": "UNIVEN",
            "website": "https://www.univen.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-07-31"
            },
            "notes": "Provisional dates - confirm with university",
            "type": "public",
            "province": "other"
          },
          {
            "name": "University of Fort Hare",
            "short": "UFH",
            "website": "https://www.ufh.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-07-31"
            },
            "notes": "Provisional dates - confirm with university",
            "type": "public",
            "province": "eastern-cape"
          },
          {
            "name": "Tshwane University of Technology",
            "short": "TUT",
            "website": "https://www.tut.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-07-31"
            },
            "notes": "Provisional dates - confirm with university",
            "type": "public",
            "province": "gauteng"
          },
          {
            "name": "Cape Peninsula University of Technology",
            "short": "CPUT",
            "website": "https://www.cput.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-07-31"
            },
            "notes": "Provisional dates - confirm with university",
            "type": "public",
            "province": "western-cape"
          },
          {
            "name": "Durban University of Technology",
            "short": "DUT",
            "website": "https://www.dut.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-07-31"
            },
            "notes": "Provisional dates - confirm with university",
            "type": "public",
            "province": "kwazulu-natal"
          },
          {
            "name": "Vaal University of Technology",
            "short": "VUT",
            "website": "https://www.vut.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-07-31"
            },
            "notes": "Provisional dates - confirm with university",
            "type": "public",
            "province": "gauteng"
          },
          {
            "name": "Central University of Technology",
            "short": "CUT",
            "website": "https://www.cut.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-07-31"
            },
            "notes": "Provisional dates - confirm with university",
            "type": "public",
            "province": "other"
          },
          {
            "name": "Mangosuthu University of Technology",
            "short": "MUT",
            "website": "https://www.mut.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-07-31"
            },
            "notes": "Provisional dates - confirm with university",
            "type": "public",
            "province": "kwazulu-natal"
          },
          {
            "name": "Sol Plaatje University",
            "short": "SPU",
            "website": "https://www.spu.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-07-31"
            },
            "notes": "Provisional dates - confirm with university",
            "type": "public",
            "province": "other"
          },
          {
            "name": "University of Mpumalanga",
            "short": "UMP",
            "website": "https://www.ump.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-07-31"
            },
            "notes": "Provisional dates - confirm with university",
            "type": "public",
            "province": "other"
          },
          {
            "name": "Sefako Makgatho Health Sciences University",
            "short": "SMU",
            "website": "https://www.smu.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-07-31"
            },
            "notes": "Health sciences specialist - provisional dates",
            "type": "public",
            "province": "gauteng"
          },
          {
            "name": "Monash South Africa",
            "short": "Monash SA",
            "website": "https://www.monash.ac.za",
            "qs2026_global_rank": 42,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-07-31"
            },
            "notes": "Private university - provisional dates",
            "type": "private",
            "province": "gauteng"
          },
          {
            "name": "Boston City Campus",
            "short": "BCC",
            "website": "https://www.boston.co.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-11-30"
            },
            "notes": "Private institution - multiple intake periods",
            "type": "private",
            "province": "other"
          },
          {
            "name": "Damelin",
            "short": "Damelin",
            "website": "https://www.damelin.co.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-11-30"
            },
            "notes": "Private institution - multiple intake periods",
            "type": "private",
            "province": "other"
          },
          {
            "name": "Varsity College",
            "short": "VC",
            "website": "https://www.varsitycollege.co.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-11-30"
            },
            "notes": "Private institution - multiple intake periods",
            "type": "private",
            "province": "other"
          },
          {
            "name": "Rosebank College",
            "short": "Rosebank",
            "website": "https://www.rosebankcollege.co.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-11-30"
            },
            "notes": "Private institution - multiple intake periods",
            "type": "private",
            "province": "other"
          },
          {
            "name": "Milpark Education",
            "short": "Milpark",
            "website": "https://www.milpark.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-11-30"
            },
            "notes": "Private institution - multiple intake periods",
            "type": "private",
            "province": "gauteng"
          },
          {
            "name": "The Independent Institute of Education",
            "short": "IIE",
            "website": "https://www.iie.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-11-30"
            },
            "notes": "Private institution - multiple intake periods",
            "type": "private",
            "province": "other"
          },
          {
            "name": "AFDA",
            "short": "AFDA",
            "website": "https://www.afda.co.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-10-31"
            },
            "notes": "Creative arts specialist - extended periods",
            "type": "private",
            "province": "other"
          },
          {
            "name": "Inscape Design College",
            "short": "Inscape",
            "website": "https://www.inscape.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-10-31"
            },
            "notes": "Design specialist - extended periods",
            "type": "private",
            "province": "other"
          },
          {
            "name": "Vega School",
            "short": "Vega",
            "website": "https://www.vegaschool.com",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-10-31"
            },
            "notes": "Creative industries specialist",
            "type": "private",
            "province": "other"
          },
          {
            "name": "Red & Yellow Creative School of Business",
            "short": "Red & Yellow",
            "website": "https://www.redandyellow.co.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-10-31"
            },
            "notes": "Creative business specialist",
            "type": "private",
            "province": "western-cape"
          },
          {
            "name": "Richfield Graduate Institute of Technology",
            "short": "Richfield",
            "website": "https://www.richfield.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-11-30"
            },
            "notes": "Private institution - multiple intake periods",
            "type": "private",
            "province": "other"
          },
          {
            "name": "CTI Education Group",
            "short": "CTI",
            "website": "https://www.cti.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-11-30"
            },
            "notes": "Private institution - multiple intake periods",
            "type": "private",
            "province": "other"
          },
          {
            "name": "Pearson Institute of Higher Education",
            "short": "Pearson",
            "website": "https://www.pearson.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-11-30"
            },
            "notes": "Private institution - multiple intake periods",
            "type": "private",
            "province": "other"
          },
          {
            "name": "Regent Business School",
            "short": "Regent",
            "website": "https://www.regent.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-11-30"
            },
            "notes": "Private business school - multiple intakes",
            "type": "private",
            "province": "other"
          },
          {
            "name": "Mancosa",
            "short": "Mancosa",
            "website": "https://www.mancosa.co.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-11-30"
            },
            "notes": "Private institution - distance learning focus",
            "type": "private",
            "province": "other"
          },
          {
            "name": "University of Technology Sydney (South Africa)",
            "short": "UTS SA",
            "website": "https://www.uts.edu.au/about/uts-global/south-africa",
            "qs2026_global_rank": 90,
            "applications": {
              "undergrad_open": "2025-02-01",
              "undergrad_close": "2025-10-31"
            },
            "notes": "International campus - technology focus",
            "type": "private",
            "province": "gauteng"
          },
          {
            "name": "Eduvos",
            "short": "Eduvos",
            "website": "https://www.eduvos.com",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-11-30"
            },
            "notes": "Private institution - multiple campuses",
            "type": "private",
            "province": "other"
          },
          {
            "name": "Stadio Higher Education",
            "short": "Stadio",
            "website": "https://www.stadio.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-11-30"
            },
            "notes": "Private institution - multiple intake periods",
            "type": "private",
            "province": "other"
          },
          {
            "name": "Capsicum Culinary Studio",
            "short": "Capsicum",
            "website": "https://www.capsicum.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-10-31"
            },
            "notes": "Culinary arts specialist",
            "type": "private",
            "province": "western-cape"
          },
          {
            "name": "The Design School Southern Africa",
            "short": "DSSA",
            "website": "https://www.designschool.co.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-10-31"
            },
            "notes": "Design specialist institution",
            "type": "private",
            "province": "other"
          },
          {
            "name": "Academy of Sound Engineering",
            "short": "ASE",
            "website": "https://www.ase.co.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-10-31"
            },
            "notes": "Audio engineering specialist",
            "type": "private",
            "province": "other"
          },
          {
            "name": "Open Window Institute",
            "short": "Open Window",
            "website": "https://www.openwindow.co.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-10-31"
            },
            "notes": "Creative arts and technology",
            "type": "private",
            "province": "gauteng"
          },
          {
            "name": "Greenside Design Center",
            "short": "Greenside",
            "website": "https://www.greenside.co.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-10-31"
            },
            "notes": "Design and digital arts specialist",
            "type": "private",
            "province": "gauteng"
          },
          {
            "name": "Stellenbosch Academy of Design and Photography",
            "short": "SADP",
            "website": "https://www.stellenboschacademy.co.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-10-31"
            },
            "notes": "Design and photography specialist",
            "type": "private",
            "province": "western-cape"
          },
          {
            "name": "Elizabeth Galloway Academy of Fashion Design",
            "short": "EGA",
            "website": "https://www.elizabethgalloway.co.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-10-31"
            },
            "notes": "Fashion design specialist",
            "type": "private",
            "province": "western-cape"
          },
          {
            "name": "Lisof Fashion Design School",
            "short": "Lisof",
            "website": "https://www.lisof.co.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-10-31"
            },
            "notes": "Fashion design specialist",
            "type": "private",
            "province": "gauteng"
          },
          {
            "name": "Oakfields College",
            "short": "Oakfields",
            "website": "https://www.oakfields.co.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-11-30"
            },
            "notes": "Private college - multiple programs",
            "type": "private",
            "province": "other"
          },
          {
            "name": "Lyceum College",
            "short": "Lyceum",
            "website": "https://www.lyceum.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-11-30"
            },
            "notes": "Private institution - business focus",
            "type": "private",
            "province": "other"
          },
          {
            "name": "Cornerstone Institute",
            "short": "Cornerstone",
            "website": "https://www.cornerstone.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-11-30"
            },
            "notes": "Private Christian institution",
            "type": "private",
            "province": "western-cape"
          },
          {
            "name": "Regenesys Business School",
            "short": "Regenesys",
            "website": "https://www.regenesys.net",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-11-30"
            },
            "notes": "Private business school",
            "type": "private",
            "province": "gauteng"
          },
          {
            "name": "Wits Business School",
            "short": "WBS",
            "website": "https://www.wbs.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-03-01",
              "undergrad_close": "2025-08-31"
            },
            "notes": "Part of University of the Witwatersrand",
            "type": "public",
            "province": "gauteng"
          },
          {
            "name": "UCT Graduate School of Business",
            "short": "UCT GSB",
            "website": "https://www.gsb.uct.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-04-01",
              "undergrad_close": "2025-07-31"
            },
            "notes": "Part of University of Cape Town",
            "type": "public",
            "province": "western-cape"
          },
          {
            "name": "Henley Business School Africa",
            "short": "Henley Africa",
            "website": "https://www.henley.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-11-30"
            },
            "notes": "International business school",
            "type": "private",
            "province": "gauteng"
          },
          {
            "name": "GIBS Business School",
            "short": "GIBS",
            "website": "https://www.gibs.co.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-02-01",
              "undergrad_close": "2025-10-31"
            },
            "notes": "Gordon Institute of Business Science",
            "type": "private",
            "province": "gauteng"
          },
          {
            "name": "Midrand Graduate Institute",
            "short": "MGI",
            "website": "https://www.mgi.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-11-30"
            },
            "notes": "Private institution - business focus",
            "type": "private",
            "province": "gauteng"
          },
          {
            "name": "IMM Graduate School of Marketing",
            "short": "IMM GSM",
            "website": "https://www.immgsm.ac.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-11-30"
            },
            "notes": "Marketing specialist institution",
            "type": "private",
            "province": "other"
          },
          {
            "name": "Cranfield University (South Africa)",
            "short": "Cranfield SA",
            "website": "https://www.cranfield.ac.uk/som/locations/south-africa",
            "qs2026_global_rank": 223,
            "applications": {
              "undergrad_open": "2025-02-01",
              "undergrad_close": "2025-09-30"
            },
            "notes": "International campus - management focus",
            "type": "private",
            "province": "gauteng"
          },
          {
            "name": "MANCOSA Graduate School of Business",
            "short": "MANCOSA GSB",
            "website": "https://www.mancosa.co.za/gsb",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-11-30"
            },
            "notes": "Business school division of MANCOSA",
            "type": "private",
            "province": "kwazulu-natal"
          },
          {
            "name": "Optimi College",
            "short": "Optimi",
            "website": "https://www.optimi.co.za",
            "qs2026_global_rank": null,
            "applications": {
              "undergrad_open": "2025-01-15",
              "undergrad_close": "2025-11-30"
            },
            "notes": "Private institution - multiple programs",
            "type": "private",
            "province": "other"
          }
        ];

        // Format application dates for display
        function formatApplicationDates(applications) {
            if (!applications.undergrad_open || !applications.undergrad_close) {
                return "Check university website";
            }
            
            let openDate = applications.undergrad_open;
            let closeDate = applications.undergrad_close;
            
            if (openDate === "TBA" || closeDate === "TBA") {
                return "Dates TBA - Check website";
            }
            
            // Format dates for better readability
            const formatDate = (dateStr) => {
                const date = new Date(dateStr);
                return date.toLocaleDateString('en-ZA', { day: 'numeric', month: 'short', year: 'numeric' });
            };
            
            return `${formatDate(openDate)} - ${formatDate(closeDate)}`;
        }

        // Get ranking badge class
        function getRankingBadge(rank) {
            if (!rank) return "rank-other";
            if (rank <= 200) return "rank-1";
            if (rank <= 300) return "rank-2";
            if (rank <= 400) return "rank-3";
            if (rank <= 500) return "rank-top";
            if (rank <= 1000) return "rank-good";
            return "rank-other";
        }

        // Get ranking display text
        function getRankingText(rank) {
            if (!rank) return "Not ranked";
            return `#${rank} globally`;
        }

        // Render universities based on filters
        function renderUniversities() {
            const typeFilter = document.getElementById('typeFilter').value;
            const rankingFilter = document.getElementById('rankingFilter').value;
            const provinceFilter = document.getElementById('provinceFilter').value;
            const searchText = document.getElementById('searchInput').value.toLowerCase();
            
            const container = document.getElementById('universitiesContainer');
            const noResults = document.getElementById('noResults');
            
            // Filter universities
            const filteredUniversities = universities.filter(uni => {
                // Type filter
                if (typeFilter !== 'all' && uni.type !== typeFilter) return false;
                
                // Ranking filter
                if (rankingFilter === 'top500' && (!uni.qs2026_global_rank || uni.qs2026_global_rank > 500)) return false;
                if (rankingFilter === 'top1000' && (!uni.qs2026_global_rank || uni.qs2026_global_rank > 1000)) return false;
                if (rankingFilter === 'unranked' && uni.qs2026_global_rank) return false;
                
                // Province filter
                if (provinceFilter !== 'all') {
                    if (provinceFilter === 'other' && 
                        ['gauteng', 'western-cape', 'eastern-cape', 'kwazulu-natal'].includes(uni.province)) {
                        return false;
                    }
                    if (provinceFilter !== 'other' && uni.province !== provinceFilter) return false;
                }
                
                // Search filter
                if (searchText && !uni.name.toLowerCase().includes(searchText) && 
                    !uni.short.toLowerCase().includes(searchText)) {
                    return false;
                }
                
                return true;
            });
            
            // Sort by QS ranking (ranked first, then alphabetical)
            filteredUniversities.sort((a, b) => {
                if (a.qs2026_global_rank && b.qs2026_global_rank) {
                    return a.qs2026_global_rank - b.qs2026_global_rank;
                }
                if (a.qs2026_global_rank && !b.qs2026_global_rank) return -1;
                if (!a.qs2026_global_rank && b.qs2026_global_rank) return 1;
                return a.name.localeCompare(b.name);
            });
            
            // Clear container
            container.innerHTML = '';
            
            // Show no results message if needed
            if (filteredUniversities.length === 0) {
                noResults.classList.remove('hidden');
                return;
            }
            
            noResults.classList.add('hidden');
            
            // Render universities
            filteredUniversities.forEach(uni => {
                const card = document.createElement('div');
                card.className = 'university-card bg-white rounded-lg shadow-md p-6 fade-in';
                
                card.innerHTML = `
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="text-xl font-semibold text-gray-900">${uni.name}</h3>
                        ${uni.qs2026_global_rank ? `
                            <span class="rank-badge ${getRankingBadge(uni.qs2026_global_rank)}" title="${getRankingText(uni.qs2026_global_rank)}">
                                ${uni.qs2026_global_rank <= 1000 ? uni.qs2026_global_rank : '1k+'}
                            </span>
                        ` : ''}
                    </div>
                    
                    <div class="mb-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                            ${uni.type === 'public' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'}">
                            ${uni.type === 'public' ? 'Public University' : 'Private Institution'}
                        </span>
                    </div>
                    
                    <div class="mb-4">
                        <div class="flex items-center text-sm text-gray-600 mb-1">
                            <i data-lucide="calendar" class="h-4 w-4 mr-1"></i>
                            <span class="font-medium">Applications:</span>
                        </div>
                        <div class="text-sm text-gray-800">${formatApplicationDates(uni.applications)}</div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="text-sm text-gray-600">${uni.notes}</div>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <a href="${uni.website}" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                            Visit Website
                            <i data-lucide="external-link" class="h-4 w-4 ml-1"></i>
                        </a>
                        <button class="text-green-600 hover:text-green-800 text-sm font-medium flex items-center">
                            Save
                            <i data-lucide="bookmark" class="h-4 w-4 ml-1"></i>
                        </button>
                    </div>
                `;
                
                container.appendChild(card);
            });
            
            // Re-initialize icons for new elements
            lucide.createIcons();
        }

        // Check login status and update navigation
        function updateNavigation() {
            // Check if user is logged in (you can modify this logic based on your session management)
            const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
            
            const loginLink = document.querySelector('a[href="student-login.php"]');
            const signupLink = document.querySelector('a[href="create-profile.php"]');
            const logoutLink = document.getElementById('logout-link');
            
            if (isLoggedIn) {
                // Hide login and signup, show logout
                if (loginLink) loginLink.style.display = 'none';
                if (signupLink) signupLink.style.display = 'none';
                if (logoutLink) logoutLink.style.display = 'block';
            } else {
                // Show login and signup, hide logout
                if (loginLink) loginLink.style.display = 'block';
                if (signupLink) signupLink.style.display = 'block';
                if (logoutLink) logoutLink.style.display = 'none';
            }
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Update navigation based on login status
            updateNavigation();
            
            // Render all universities initially
            renderUniversities();
            
            // Add event listeners to filters
            document.getElementById('typeFilter').addEventListener('change', renderUniversities);
            document.getElementById('rankingFilter').addEventListener('change', renderUniversities);
            document.getElementById('provinceFilter').addEventListener('change', renderUniversities);
            document.getElementById('searchInput').addEventListener('input', renderUniversities);
        });
    </script>
</body>
</html>