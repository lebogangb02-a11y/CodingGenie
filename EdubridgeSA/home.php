<?php 
require_once 'auth.php';

// Check if user is logged in, redirect to login if not
if (!isLoggedIn()) {
    header("Location: student-login.php");
    exit;
}

// Get current user info
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>EduBridgeSA - Application Form</title>
  <meta name="description" content="Apply for university assistance through EduBridgeSA. We help students navigate higher education applications in South Africa." />
  <!-- Base64-encoded 16x16 transparent PNG favicon -->
  <link rel="icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAOwwAADsMBx2+oZAAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAABnSURBVDiNY2AYaMAIxX///mWgVgD0wH8oZgTjf//+MTAzM4MpRkZGBgYGBgZGRkYwZoTyGRkZGRgZGRn+//8P5oMk//37B8b//v0D4//QcPj37x8Yg2T+/v0LZv/58weM//79C8Z//vxh+P37N8Pv37/B/N+/fzP8+vWL4efPn2D848cPMP7+/TvD9+/fGb59+8bw9etXhi9fvjB8/vyZ4dOnTwwfP35k+PDhA8P79+8Z3r17x/D27VuGN2/eMLx+/Zrh1atXDC9fvmR48eIFw/PnzxmePXvG8PTpU4YnT54wPH78mOHRo0cMDx8+ZHjw4AHD/fv3Ge7du8dw9+5dhjt37jDcvn2b4datWww3b95kuHHjBsP169cZrl27xnD16lWGK1euMFy+fJnh0qVLDBcvXmS4cOECw/nz5xnOnTvHcPbsWYYzZ84wnD59muHUqVMMJ0+eZDhx4gTD8ePHGY4dO8Zw9OhRhiNHjjAcPnyY4dChQwwHDx5k2L9/P8O+ffsY9u7dy7Bnzx6G3bt3M+zatYth586dDNu3b2fYtm0bw9atWxk2b97MsGnTJoaNGzcybNiwgWH9+vUM69atY1i7di3DmjVrGFavXs2watUqhpUrVzKsWLGCYfny5QxLly5lWLJkCcPixYsZFi1axLBw4UKGBQsWMMyfP59h3rx5DHPnzmWYM2cOw+zZsxlmzZrFMHPmTIYZM2YwTJ8+nWHatGkMU6dOZZgyZQrD5MmTGSZNmsQwceJEhgkTJjCMHz+eYdy4cQxjx45lGDNmDMPo0aMZRo0axTBy5EiGESNGMAwfPpxh2LBhDEOHDmUYMmQIw+DBgxkGDRrEMHDgQIYBAwYw9O/fn6Ffv34Mffv2ZejTpw9D7969GXr16sXQs2dPhh49ejB0796doVu3bgxdu3Zl6NKlC0Pnzp0ZOnXqxNCxY0eGDh06MLRv356hXbt2DG3btmVo06YNQ+vWrRlatWrF0LJlS4YWLVowNG/enKFZs2YMjRs3ZmjUqBFDw4YNGerXr89Qr149hrp16zLUqVOHoXbt2gy1atViqFmzJkONGjUYqlevzlCtWjWGqlWrMlSpUoWhcuXKDJUqVWKoWLEiQ4UKFRjKly/PUK5cOYayZcsylClThqF06dIMpUqVYihZsiRDiRIlGIoXL85QrFgxhqJFizIUKVKEoXDhwgyFChViKFiwIEOBAgUY8ufPz5AvXz6GvHnzMuTJk4chd+7cDLly5WLIkSMHQ/bs2RmyZcvGkDVrVoYsWbIwZM6cmSFTpkwMGTNmZMiQIQND+vTpGdKlS8eQNm1ahjRp0jCkTp2aIVWqVAwpU6ZkSJEiBUPy5MkZkiVLxpA0aVKGxIkTMyRKlIghYcKEDAkSJGBgYGBgAADqkD3QO3g4bQAAAABJRU5ErkJggg==" type="image/x-icon">

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest"></script>

  <!-- Flatpickr for date picker -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

  <!-- Select2 for searchable dropdowns -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

  <style>
    :root {
      --primary: #1a5fb4;
      --primary-dark: #0f4a8f;
      --secondary: #ff7b00;
      --accent: #2ecc71;
    }

    body {
      font-family: 'Inter', sans-serif;
      color: #333;
      background: #f8fafc;
    }

    .form-step {
      display: none;
      animation: fadeIn 0.5s ease;
    }

    .form-step.active {
      display: block;
    }

    .step-indicator {
      display: flex;
      justify-content: space-between;
      margin: 2rem 0;
      position: relative;
    }

    .step-indicator::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 0;
      right: 0;
      height: 4px;
      background: #e2e8f0;
      transform: translateY(-50%);
      z-index: 1;
    }

    .step-indicator-progress {
      position: absolute;
      top: 50%;
      left: 0;
      height: 4px;
      background: var(--primary);
      transform: translateY(-50%);
      z-index: 2;
      transition: width 0.3s ease;
    }

    .step {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: #e2e8f0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      color: #64748b;
      position: relative;
      z-index: 3;
      transition: all 0.3s ease;
    }

    .step.active {
      background: var(--primary);
      color: white;
    }

    .step.completed {
      background: var(--accent);
      color: white;
    }

    .step-label {
      position: absolute;
      top: 45px;
      left: 50%;
      transform: translateX(-50%);
      font-size: 0.75rem;
      font-weight: 600;
      color: #64748b;
      white-space: nowrap;
    }

    .step.active .step-label {
      color: var(--primary);
      font-weight: 700;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* File upload styling */
    .file-upload {
      border: 2px dashed #cbd5e1;
      border-radius: 0.5rem;
      padding: 1.5rem;
      text-align: center;
      transition: all 0.3s;
      margin-bottom: 1rem;
    }

    .file-upload:hover {
      border-color: var(--primary);
      background-color: #f8fafc;
    }

    .file-upload input[type="file"] {
      display: none;
    }

    .file-upload-label {
      display: flex;
      flex-direction: column;
      align-items: center;
      cursor: pointer;
    }

    .file-upload-label i {
      font-size: 2rem;
      color: var(--primary);
      margin-bottom: 0.5rem;
    }

    .file-name {
      margin-top: 0.5rem;
      font-size: 0.875rem;
      color: #64748b;
    }

    /* Custom checkbox */
    .custom-checkbox {
      display: flex;
      align-items: flex-start;
      margin-bottom: 1rem;
    }

    .custom-checkbox input[type="checkbox"] {
      margin-right: 0.75rem;
      margin-top: 0.25rem;
    }

    /* Responsive adjustments */
    @media (max-width: 640px) {
      .step-indicator {
        padding: 0 1rem;
      }
      
      .step {
        width: 32px;
        height: 32px;
        font-size: 0.875rem;
      }
      
      .step-label {
        font-size: 0.65rem;
      }
    }
  </style>
</head>
<body class="antialiased">
  <!-- Navbar -->
  <nav class="bg-white shadow-md fixed w-full z-50">
    <div class="max-w-7xl mx-auto flex justify-between items-center px-6 h-16">
      <div class="flex items-center gap-3">
        <img src="logo.png" alt="EduBridgeSA Logo" onerror="this.src='https://placehold.co/100x60/1a5fb4/ffffff?text=EB'" class="h-10" />
        <span class="text-lg font-bold text-blue-700">EduBridgeSA</span>
      </div>
      <div class="hidden md:flex gap-6 text-gray-600 font-medium">
        <a href="index.html" class="hover:text-blue-600">Home</a>
        <a href="about.html" class="hover:text-blue-600">About</a>
        <a href="services.html" class="hover:text-blue-600">Services</a>
        <a href="universities.html" class="hover:text-blue-600">Universities</a>
        <a href="tvet.html" class="hover:text-blue-600">TVET</a>
        <a href="resources.html" class="hover:text-blue-600">Resources</a>
        <a href="contact.html" class="hover:text-blue-600">Contact</a>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <main class="pt-20 pb-16 px-4">
    <div class="max-w-4xl mx-auto bg-white rounded-xl shadow-md overflow-hidden">
      <!-- Form Header -->
      <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white p-6 md:p-8">
        <h1 class="text-2xl md:text-3xl font-bold mb-2">University Application Form</h1>
        <p class="text-blue-100">Complete the form below to apply for university assistance through EduBridgeSA</p>
      </div>

      <!-- Progress Indicator -->
      <div class="px-6 pt-6">
        <div class="step-indicator">
          <div class="step-indicator-progress" id="progressBar"></div>
          <div class="step active" data-step="1">
            <span>1</span>
            <span class="step-label">Personal</span>
          </div>
          <div class="step" data-step="2">
            <span>2</span>
            <span class="step-label">Parent/Guardian</span>
          </div>
          <div class="step" data-step="3">
            <span>3</span>
            <span class="step-label">Education</span>
          </div>
          <div class="step" data-step="4">
            <span>4</span>
            <span class="step-label">Documents</span>
          </div>
          <div class="step" data-step="5">
            <span>5</span>
            <span class="step-label">Review</span>
          </div>
        </div>
      </div>

      <!-- Form -->
      <form id="applicationForm" class="p-6 md:p-8" enctype="multipart/form-data">
        <!-- Step 1: Personal Details -->
        <div class="form-step active" id="step-1">
          <h2 class="text-xl font-bold mb-6 text-gray-800">Personal Information</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="fullName" class="block text-sm font-medium text-gray-700 mb-1">Full Name(s) <span class="text-red-500">*</span></label>
              <input type="text" id="fullName" name="fullName" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
            </div>
            
            <div>
              <label for="surname" class="block text-sm font-medium text-gray-700 mb-1">Surname <span class="text-red-500">*</span></label>
              <input type="text" id="surname" name="surname" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
            </div>
            
            <div>
              <label for="idNumber" class="block text-sm font-medium text-gray-700 mb-1">ID Number <span class="text-red-500">*</span></label>
              <input type="text" id="idNumber" name="idNumber" pattern="[0-9]{13}" title="Please enter a valid 13-digit ID number" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
              <p class="mt-1 text-xs text-gray-500">Enter your 13-digit South African ID number</p>
            </div>
            
            <div>
              <label for="dateOfBirth" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth <span class="text-red-500">*</span></label>
              <input type="date" id="dateOfBirth" name="dateOfBirth" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required
                onchange="console.log('Date changed:', this.value); checkAgeAndProceed(this.value);"
                oninput="console.log('Date input:', this.value);">
              <div id="ageDebug" class="text-sm text-gray-500 mt-1"></div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Gender <span class="text-red-500">*</span></label>
              <div class="grid grid-cols-2 gap-4">
                <label class="inline-flex items-center">
                  <input type="radio" name="gender" value="male" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300" required>
                  <span class="ml-2 text-gray-700">Male</span>
                </label>
                <label class="inline-flex items-center">
                  <input type="radio" name="gender" value="female" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                  <span class="ml-2 text-gray-700">Female</span>
                </label>
                <label class="inline-flex items-center">
                  <input type="radio" name="gender" value="other" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                  <span class="ml-2 text-gray-700">Other</span>
                </label>
                <div id="otherGenderContainer" class="hidden col-span-2">
                  <input type="text" id="otherGender" name="otherGender" placeholder="Please specify" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
              </div>
            </div>
            
            <div>
              <label for="cellphone" class="block text-sm font-medium text-gray-700 mb-1">Cellphone Number <span class="text-red-500">*</span></label>
              <div class="mt-1 flex rounded-md shadow-sm">
                <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">+27</span>
                <input type="tel" id="cellphone" name="cellphone" pattern="[0-9]{9}" title="Please enter a valid 9-digit cellphone number" class="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-r-md border border-gray-300 focus:ring-blue-500 focus:border-blue-500" placeholder="71 234 5678" required>
              </div>
              <p class="mt-1 text-xs text-gray-500">Enter your 9-digit cellphone number (without the first 0)</p>
            </div>
            
            <div>
              <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-red-500">*</span></label>
              <input type="email" id="email" name="email" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
            </div>
            
            <div class="md:col-span-2">
              <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Physical Address</label>
              <textarea id="address" name="address" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
            
            <div>
              <label for="postalCode" class="block text-sm font-medium text-gray-700 mb-1">Postal Code <span class="text-red-500">*</span></label>
              <input type="text" id="postalCode" name="postalCode" pattern="[0-9]{4}" title="Please enter a valid 4-digit postal code" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
            </div>
            
            <div>
              <label for="country" class="block text-sm font-medium text-gray-700 mb-1">Country of Residence <span class="text-red-500">*</span></label>
              <select id="country" name="country" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                <option value="South Africa" selected>South Africa</option>
                <option value="Lesotho">Lesotho</option>
                <option value="Botswana">Botswana</option>
                <option value="Eswatini">Eswatini</option>
                <option value="Namibia">Namibia</option>
                <option value="Zimbabwe">Zimbabwe</option>
                <option value="Mozambique">Mozambique</option>
                <option value="Other">Other</option>
              </select>
            </div>
            
            <div class="md:col-span-2">
              <label for="school" class="block text-sm font-medium text-gray-700 mb-1">Name of Your School</label>
              <input type="text" id="school" name="school" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
          </div>
          
          <div class="mt-8 flex justify-between">
            <div class="hidden md:block"></div> <!-- Spacer for alignment -->
            <button type="button" class="next-step bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-md transition duration-150 ease-in-out flex items-center">
              Next <i data-lucide="arrow-right" class="ml-2 w-4 h-4"></i>
            </button>
          </div>
        </div>

        <!-- Step 2: Parent/Guardian Details -->
        <div class="form-step" id="step-2">
          <h2 class="text-xl font-bold mb-6 text-gray-800">Parent/Guardian Details</h2>
          <p class="text-gray-600 mb-6">This section is required for applicants under 18 years old.</p>
          
          <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label for="parentFullName" class="block text-sm font-medium text-gray-700 mb-1">Full Names <span class="text-red-500">*</span></label>
                <input type="text" id="parentFullName" name="parentFullName" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              </div>
              
              <div>
                <label for="parentSurname" class="block text-sm font-medium text-gray-700 mb-1">Surname <span class="text-red-500">*</span></label>
                <input type="text" id="parentSurname" name="parentSurname" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              </div>
              
              <div>
                <label for="relationship" class="block text-sm font-medium text-gray-700 mb-1">Relationship to Applicant <span class="text-red-500">*</span></label>
                <select id="relationship" name="relationship" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                  <option value="">Select Relationship</option>
                  <option value="Mother">Mother</option>
                  <option value="Father">Father</option>
                  <option value="Guardian">Guardian</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              
              <div>
                <label for="parentIdNumber" class="block text-sm font-medium text-gray-700 mb-1">ID Number</label>
                <input type="text" id="parentIdNumber" name="parentIdNumber" pattern="[0-9]{13}" title="Please enter a valid 13-digit ID number" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              </div>
              
              <div>
                <label for="parentCellphone" class="block text-sm font-medium text-gray-700 mb-1">Cellphone Number <span class="text-red-500">*</span></label>
                <div class="flex rounded-md shadow-sm">
                  <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">+27</span>
                  <input type="tel" id="parentCellphone" name="parentCellphone" pattern="[0-9]{9}" title="Please enter a valid 9-digit cellphone number" class="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-r-md border border-gray-300 focus:ring-blue-500 focus:border-blue-500" placeholder="71 234 5678">
                </div>
              </div>
              
              <div>
                <label for="parentEmail" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                <input type="email" id="parentEmail" name="parentEmail" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              </div>
            </div>
          </div>
          
          <div class="mt-8 flex flex-col-reverse md:flex-row justify-between gap-4">
            <button type="button" class="prev-step bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-6 rounded-md transition duration-150 ease-in-out flex items-center justify-center">
              <i data-lucide="arrow-left" class="mr-2 w-4 h-4"></i> Previous
            </button>
            <button type="button" class="next-step bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-md transition duration-150 ease-in-out flex items-center justify-center">
              Next <i data-lucide="arrow-right" class="ml-2 w-4 h-4"></i>
            </button>
          </div>
        </div>

        <!-- Step 3: Education Details -->
        <div class="form-step" id="step-3">
          <h2 class="text-xl font-bold mb-6 text-gray-800">Education Details</h2>
          
          <div class="space-y-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Select Up to 3 Universities/Colleges <span class="text-red-500">*</span></label>
              <select id="universities" name="universities[]" class="w-full select2" multiple="multiple" required>
                <optgroup label="Traditional Universities">
                  <option value="University of Cape Town (UCT)">University of Cape Town (UCT)</option>
                  <option value="University of the Witwatersrand (Wits)">University of the Witwatersrand (Wits)</option>
                  <option value="Stellenbosch University">Stellenbosch University</option>
                  <option value="University of Pretoria (UP)">University of Pretoria (UP)</option>
                  <option value="University of Johannesburg (UJ)">University of Johannesburg (UJ)</option>
                  <option value="University of KwaZulu-Natal (UKZN)">University of KwaZulu-Natal (UKZN)</option>
                  <option value="North-West University (NWU)">North-West University (NWU)</option>
                  <option value="University of South Africa (UNISA)">University of South Africa (UNISA)</option>
                  <option value="University of the Free State (UFS)">University of the Free State (UFS)</option>
                  <option value="University of Limpopo">University of Limpopo</option>
                  <option value="University of Venda">University of Venda</option>
                  <option value="University of Zululand">University of Zululand</option>
                  <option value="Walter Sisulu University">Walter Sisulu University</option>
                </optgroup>
                <optgroup label="Universities of Technology">
                  <option value="Tshwane University of Technology (TUT)">Tshwane University of Technology (TUT)</option>
                  <option value="Cape Peninsula University of Technology (CPUT)">Cape Peninsula University of Technology (CPUT)</option>
                  <option value="Durban University of Technology (DUT)">Durban University of Technology (DUT)</option>
                  <option value="Mangosuthu University of Technology (MUT)">Mangosuthu University of Technology (MUT)</option>
                  <option value="Vaal University of Technology (VUT)">Vaal University of Technology (VUT)</option>
                  <option value="Central University of Technology (CUT)">Central University of Technology (CUT)</option>
                </optgroup>
                <optgroup label="TVET Colleges">
                  <option value="Buffalo City TVET College">Buffalo City TVET College</option>
                  <option value="Cape Town TVET College">Cape Town TVET College</option>
                  <option value="Central Johannesburg TVET College">Central Johannesburg TVET College</option>
                  <option value="Durban University of Technology (DUT)">Durban University of Technology (DUT)</option>
                  <option value="Ekurhuleni East TVET College">Ekurhuleni East TVET College</option>
                  <option value="False Bay TVET College">False Bay TVET College</option>
                  <option value="Gert Sibande TVET College">Gert Sibande TVET College</option>
                  <option value="Majuba TVET College">Majuba TVET College</option>
                  <option value="Motheo TVET College">Motheo TVET College</option>
                  <option value="Northern Cape Rural TVET College">Northern Cape Rural TVET College</option>
                  <option value="Orbit TVET College">Orbit TVET College</option>
                  <option value="Sedibeng TVET College">Sedibeng TVET College</option>
                  <option value="South West Gauteng TVET College">South West Gauteng TVET College</option>
                  <option value="Tshwane North TVET College">Tshwane North TVET College</option>
                  <option value="Tshwane South TVET College">Tshwane South TVET College</option>
                  <option value="Vhembe TVET College">Vhembe TVET College</option>
                  <option value="Waterberg TVET College">Waterberg TVET College</option>
                  <option value="West Coast TVET College">West Coast TVET College</option>
                </optgroup>
              </select>
              <p class="mt-1 text-xs text-gray-500">Start typing to search for institutions</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label for="firstOption" class="block text-sm font-medium text-gray-700 mb-1">First Choice Course <span class="text-red-500">*</span></label>
                <input type="text" id="firstOption" name="firstOption" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="E.g. Bachelor of Education Senior and FET Phase" required>
              </div>
              
              <div>
                <label for="secondOption" class="block text-sm font-medium text-gray-700 mb-1">Second Choice Course</label>
                <input type="text" id="secondOption" name="secondOption" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="E.g. Bachelor of Commerce">
              </div>
            </div>
            
            <div>
              <label for="otherUniversity" class="block text-sm font-medium text-gray-700 mb-1">Comment with the university of your choice apart from the above mentioned</label>
              <textarea id="otherUniversity" name="otherUniversity" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
              <p class="mt-1 text-xs text-gray-500">If your preferred institution is not listed above, please provide details here</p>
            </div>
          </div>
          
          <div class="mt-8 flex flex-col-reverse md:flex-row justify-between gap-4">
            <button type="button" class="prev-step bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-6 rounded-md transition duration-150 ease-in-out flex items-center justify-center">
              <i data-lucide="arrow-left" class="mr-2 w-4 h-4"></i> Previous
            </button>
            <button type="button" class="next-step bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-md transition duration-150 ease-in-out flex items-center justify-center">
              Next <i data-lucide="arrow-right" class="ml-2 w-4 h-4"></i>
            </button>
          </div>
        </div>

        <!-- Step 4: Document Upload Notice -->
        <div class="form-step" id="step-4">
          <h2 class="text-xl font-bold mb-6 text-gray-800">Document Upload Information</h2>
          
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
            <div class="flex items-start">
              <i data-lucide="info" class="h-5 w-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0"></i>
              <div>
                <h3 class="font-medium text-blue-800 mb-2">Document Upload Process</h3>
                <p class="text-sm text-blue-700 mb-3">After submitting this application, you'll receive an email with a secure link to upload your required documents. This allows you to:</p>
                <ul class="text-sm text-blue-700 space-y-1 list-disc list-inside ml-4">
                  <li>Upload documents at your convenience</li>
                  <li>Take time to gather all required documents</li>
                  <li>Return later to complete the document submission</li>
                  <li>Track your application status</li>
                </ul>
              </div>
            </div>
          </div>
          
          <div class="bg-gray-50 rounded-lg p-6">
            <h3 class="font-medium text-gray-800 mb-3">Required Documents (for later upload):</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="flex items-center space-x-3">
                <i data-lucide="file-text" class="h-5 w-5 text-gray-600"></i>
                <span class="text-sm text-gray-700">Certified ID Copy</span>
              </div>
              <div class="flex items-center space-x-3">
                <i data-lucide="file-text" class="h-5 w-5 text-gray-600"></i>
                <span class="text-sm text-gray-700">Proof of Residential Address</span>
              </div>
              <div class="flex items-center space-x-3">
                <i data-lucide="file-text" class="h-5 w-5 text-gray-600"></i>
                <span class="text-sm text-gray-700">Latest Academic Results</span>
              </div>
              <div class="flex items-center space-x-3">
                <i data-lucide="file-text" class="h-5 w-5 text-gray-600"></i>
                <span class="text-sm text-gray-700">Parent/Guardian ID (if under 18)</span>
              </div>
            </div>
            <p class="text-xs text-gray-500 mt-3">All documents must be clear, legible, and in PDF, JPG, or PNG format (max 5MB each)</p>
          </div>
          
          <div class="mt-8 flex flex-col-reverse md:flex-row justify-between gap-4">
            <button type="button" class="prev-step bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-6 rounded-md transition duration-150 ease-in-out flex items-center justify-center">
              <i data-lucide="arrow-left" class="mr-2 w-4 h-4"></i> Previous
            </button>
            <button type="button" class="next-step bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-md transition duration-150 ease-in-out flex items-center justify-center">
              Next <i data-lucide="arrow-right" class="ml-2 w-4 h-4"></i>
            </button>
          </div>
        </div>

        <!-- Step 5: Review & Submit -->
        <div class="form-step" id="step-5">
          <h2 class="text-xl font-bold mb-6 text-gray-800">Review Your Application</h2>
          <p class="text-gray-600 mb-6">Please review your information before submitting. You can go back to make changes if needed.</p>
          
          <div class="bg-gray-50 p-6 rounded-lg mb-8">
            <h3 class="font-bold text-lg text-gray-800 mb-4 pb-2 border-b border-gray-200">Personal Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
              <div>
                <p class="text-sm text-gray-500">Full Name</p>
                <p id="reviewFullName" class="font-medium"></p>
              </div>
              <div>
                <p class="text-sm text-gray-500">ID Number</p>
                <p id="reviewIdNumber" class="font-medium"></p>
              </div>
              <div>
                <p class="text-sm text-gray-500">Date of Birth</p>
                <p id="reviewDob" class="font-medium"></p>
              </div>
              <div>
                <p class="text-sm text-gray-500">Gender</p>
                <p id="reviewGender" class="font-medium"></p>
              </div>
              <div>
                <p class="text-sm text-gray-500">Cellphone</p>
                <p id="reviewCellphone" class="font-medium"></p>
              </div>
              <div>
                <p class="text-sm text-gray-500">Email</p>
                <p id="reviewEmail" class="font-medium"></p>
              </div>
              <div class="md:col-span-2">
                <p class="text-sm text-gray-500">Address</p>
                <p id="reviewAddress" class="font-medium"></p>
              </div>
              <div>
                <p class="text-sm text-gray-500">Postal Code</p>
                <p id="reviewPostalCode" class="font-medium"></p>
              </div>
              <div>
                <p class="text-sm text-gray-500">Country</p>
                <p id="reviewCountry" class="font-medium"></p>
              </div>
              <div class="md:col-span-2">
                <p class="text-sm text-gray-500">School</p>
                <p id="reviewSchool" class="font-medium"></p>
              </div>
            </div>
            
            <div id="parentGuardianReview" class="mb-6">
              <h3 class="font-bold text-lg text-gray-800 mb-4 pb-2 border-b border-gray-200">Parent/Guardian Information</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <p class="text-sm text-gray-500">Full Name</p>
                  <p id="reviewParentName" class="font-medium"></p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">Relationship</p>
                  <p id="reviewRelationship" class="font-medium"></p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">ID Number</p>
                  <p id="reviewParentId" class="font-medium"></p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">Cellphone</p>
                  <p id="reviewParentCell" class="font-medium"></p>
                </div>
                <div class="md:col-span-2">
                  <p class="text-sm text-gray-500">Email</p>
                  <p id="reviewParentEmail" class="font-medium"></p>
                </div>
              </div>
            </div>
            
            <h3 class="font-bold text-lg text-gray-800 mb-4 pb-2 border-b border-gray-200">Education Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
              <div class="md:col-span-2">
                <p class="text-sm text-gray-500">Selected Institutions</p>
                <ul id="reviewInstitutions" class="list-disc list-inside font-medium"></ul>
              </div>
              <div class="md:col-span-2">
                <p class="text-sm text-gray-500">First Choice Course</p>
                <p id="reviewFirstOption" class="font-medium"></p>
              </div>
              <div class="md:col-span-2">
                <p class="text-sm text-gray-500">Second Choice Course</p>
                <p id="reviewSecondOption" class="font-medium"></p>
              </div>
              <div class="md:col-span-2" id="otherUniversityReviewContainer">
                <p class="text-sm text-gray-500">Other Institution</p>
                <p id="reviewOtherUniversity" class="font-medium"></p>
              </div>
            </div>
            
            <h3 class="font-bold text-lg text-gray-800 mb-4 pb-2 border-b border-gray-200">Documents</h3>
            <div class="space-y-2">
              <div class="flex items-center">
                <i data-lucide="file-text" class="h-5 w-5 text-blue-500 mr-2"></i>
                <span id="reviewIdCopy" class="text-sm">Certified ID Copy</span>
              </div>
              <div class="flex items-center">
                <i data-lucide="file-text" class="h-5 w-5 text-blue-500 mr-2"></i>
                <span id="reviewProofOfAddress" class="text-sm">Proof of Address</span>
              </div>
              <div id="reviewParentIdContainer" class="flex items-center">
                <i data-lucide="file-text" class="h-5 w-5 text-blue-500 mr-2"></i>
                <span id="reviewParentIdCopy" class="text-sm">Parent/Guardian ID</span>
              </div>
              <div class="flex items-center">
                <i data-lucide="file-text" class="h-5 w-5 text-blue-500 mr-2"></i>
                <span id="reviewAcademicResults" class="text-sm">Academic Results</span>
              </div>
            </div>
          </div>
          
          <div class="p-6 bg-blue-50 rounded-lg mb-8">
            <h3 class="font-bold text-lg text-blue-800 mb-4">PROTECTION OF PERSONAL INFORMATION ACT (POPIA) CONSENT</h3>
            <div class="bg-white p-4 rounded-md mb-4 max-h-48 overflow-y-auto text-sm text-gray-700 border border-blue-100">
              <p class="mb-3">By submitting this application form, I hereby consent to EduBridge SA processing my personal information for the purpose of my application and subsequent registration. I understand that my information will be stored securely and will only be shared with the relevant tertiary institutions I have selected and for legitimate administrative purposes. I acknowledge my right to access and correct my personal information.</p>
              <p class="font-medium">What this means:</p>
              <ul class="list-disc list-inside mt-2 space-y-1">
                <li>We will only use your information to process your application and communicate with you</li>
                <li>Your information will be shared only with the institutions you've selected</li>
                <li>We implement appropriate security measures to protect your data</li>
                <li>You have the right to access, correct, or request deletion of your personal information</li>
              </ul>
            </div>
            <div class="flex items-start">
              <div class="flex items-center h-5">
                <input id="popiaConsent" name="popiaConsent" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" required>
              </div>
              <div class="ml-3 text-sm">
                <label for="popiaConsent" class="font-medium text-gray-700">I have read and agree to the terms of the POPI Act declaration above. <span class="text-red-500">*</span></label>
              </div>
            </div>
          </div>
          
          <div class="mt-8 flex justify-between">
            <button type="button" class="prev-step bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-6 rounded-md transition duration-150 ease-in-out">Previous</button>
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-6 rounded-md transition duration-150 ease-in-out flex items-center">
              <span class="mr-2">Submit Application</span>
              <i data-lucide="send" class="h-4 w-4"></i>
            </button>
          </div>
        </div>
      </form>
    </div>
  </main>

  <!-- Footer -->
  <footer class="bg-gray-900 text-gray-300 py-10">
    <div class="max-w-7xl mx-auto px-6 grid md:grid-cols-4 gap-8">
      <div>
        <img src="logo.png" alt="EduBridgeSA Logo" class="h-14 mb-4" onerror="this.src='https://placehold.co/120x60/1a5fb4/ffffff?text=EB'" />
        <p>Your bridge to tertiary education. Guiding Grade 10–12 learners into higher learning.</p>
      </div>
      <div>
        <h4 class="font-semibold text-white mb-4">Quick Links</h4>
        <ul class="space-y-2">
          <li><a href="index.html" class="hover:text-white">Home</a></li>
          <li><a href="about.html" class="hover:text-white">About</a></li>
          <li><a href="services.html" class="hover:text-white">Services</a></li>
        </ul>
      </div>
      <div>
        <h4 class="font-semibold text-white mb-4">Resources</h4>
        <ul class="space-y-2">
          <li><a href="universities.html" class="hover:text-white">Universities</a></li>
          <li><a href="tvet.html" class="hover:text-white">TVET</a></li>
          <li><a href="aps-calculator.html" class="hover:text-white">APS Calculator</a></li>
        </ul>
      </div>
      <div>
        <h4 class="font-semibold text-white mb-4">Contact</h4>
        <ul class="space-y-2">
          <li>Email: info@edubridgesa.co.za</li>
          <li>Phone: 078 323 6239</li>
          <li>South Africa</li>
        </ul>
      </div>
    </div>
    <div class="text-center text-gray-500 mt-8">&copy; 2025 EduBridgeSA. All rights reserved.</div>
  </footer>

  <!-- Toast Notification -->
  <div id="toast" class="fixed top-4 right-4 px-6 py-3 rounded-lg font-semibold text-white z-50 transform translate-x-full transition-transform duration-300"></div>

  <!-- jQuery for Select2 -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  
  <script>
    // Main application code
    (function() {
      'use strict';
      
      // Form elements
      const form = document.getElementById('applicationForm');
      const steps = document.querySelectorAll('.form-step');
      const prevButtons = document.querySelectorAll('.prev-step');
      const nextButtons = document.querySelectorAll('.next-step');
      const progressBar = document.getElementById('progressBar');
      const stepIndicators = document.querySelectorAll('.step');
      let currentStep = 0;
      let isMinor = false;
      
      // Initialize the application when the DOM is fully loaded
      document.addEventListener('DOMContentLoaded', function() {
        // Initialize Lucide Icons
        lucide.createIcons();
        
        // Initialize Select2
        $('.select2').select2({
          maximumSelectionLength: 3,
          placeholder: 'Search and select up to 3 institutions',
          allowClear: true,
          width: '100%'
        });
        
        // Initialize date picker
        flatpickr("#dateOfBirth", {
          dateFormat: "Y-m-d",
          maxDate: "today",
          onChange: function(selectedDates, dateStr) {
            checkAgeAndProceed(dateStr);
          }
        });
        
        // Initialize form steps
        showStep(currentStep);
        updateProgressBar();
        
        // Add event listeners for navigation
        document.querySelectorAll('.next-step').forEach(button => {
          button.addEventListener('click', nextStep);
        });
        
        document.querySelectorAll('.prev-step').forEach(button => {
          button.addEventListener('click', prevStep);
        });
        
        // Add keyboard navigation
        document.addEventListener('keydown', function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            const activeElement = document.activeElement;
            // Only trigger next if we're not in a textarea or select element
            if (activeElement.tagName !== 'TEXTAREA' && activeElement.tagName !== 'SELECT') {
              const nextButton = document.querySelector('.form-step.active .next-step');
              if (nextButton) nextButton.click();
            }
          }
        });
        
        // Initialize form submission
        if (form) {
          form.addEventListener('submit', handleFormSubmit);
        }
        
        
        // Show current step and update progress
        function showStep(step) {
          steps.forEach((s, index) => {
            s.classList.toggle('active', index === step);
          });
          
          stepIndicators.forEach((indicator, index) => {
            indicator.classList.toggle('active', index <= step);
            indicator.classList.toggle('completed', index < step);
          });
        }
      
      // Update progress bar
      function updateProgressBar() {
        const progress = ((currentStep + 1) / steps.length) * 100;
        progressBar.style.width = `${progress}%`;
      }
      
      // Navigate to next step
      function nextStep() {
        if (validateStep(currentStep)) {
          if (currentStep < steps.length - 1) {
            currentStep++;
            showStep(currentStep);
            updateProgressBar();
          }
        }
      }
      
      // Navigate to previous step
      function prevStep() {
        if (currentStep > 0) {
          currentStep--;
          showStep(currentStep);
          updateProgressBar();
        }
      }
      
      // Validate current step
      function validateStep(step) {
        const currentFormStep = steps[step];
        const inputs = currentFormStep.querySelectorAll('input[required], select[required], textarea[required]');
        let isValid = true;
        
        inputs.forEach(input => {
          if (!input.value.trim()) {
            isValid = false;
            input.classList.add('border-red-500');
          } else {
            input.classList.remove('border-red-500');
          }
        });
        
        if (!isValid) {
          showToast('Please fill in all required fields', 'error');
        }
        
        return isValid;
      }
      
      // Handle form submission
      function handleFormSubmit(e) {
        e.preventDefault();
        
        if (validateStep(currentStep)) {
          const formData = new FormData(form);
          const submitButton = form.querySelector('button[type="submit"]');
          const originalText = submitButton.innerHTML;
          
          // Show loading state
          submitButton.disabled = true;
          submitButton.innerHTML = `
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Submitting...
          `;
          
          // Submit form data
          fetch('apply-handler.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              showToast('Application submitted successfully!', 'success');
              // Redirect to thank you page or show success message
              setTimeout(() => {
                window.location.href = 'thank-you.html';
              }, 2000);
            } else {
              throw new Error(data.message || 'An error occurred');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            showToast(error.message || 'Failed to submit application. Please try again.', 'error');
            // Restore button state
            if (submitButton) {
              submitButton.disabled = false;
              submitButton.innerHTML = originalHTML;
            }
          });
        }
      }
      
      // Show toast notification
      function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        if (!toast) return;
        
        toast.textContent = message;
        toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg font-semibold text-white z-50 transform transition-transform duration-300 ${type === 'success' ? 'bg-green-500' : 'bg-red-500'}`;
        
        // Show toast
        setTimeout(() => {
          toast.classList.remove('translate-x-full');
        }, 100);
        
        // Hide toast after 5 seconds
        setTimeout(() => {
          toast.classList.add('translate-x-full');
        }, 5000);
      }

      // Handle age check and form progression
      function checkAgeAndProceed(dob) {
        const debugEl = document.getElementById('ageDebug');
        debugEl.textContent = `Checking age for: ${dob || 'No date'}`;
        if (!dob) return; // Exit if no date provided
        
        try {
          console.log('Checking age for DOB:', dob);
          const minorStatus = checkMinorStatus(dob);
          isMinor = minorStatus; // Update the global isMinor variable
          debugEl.textContent += ` | Age check: ${minorStatus ? 'Minor' : 'Adult (18+)'}`;
          console.log('Is minor:', minorStatus);
        
          // Toggle parent/guardian section
          const parentSection = document.getElementById('step-2');
          if (parentSection) {
            parentSection.style.display = isMinor ? 'block' : 'none';
            
            // Toggle required fields
            const parentFields = parentSection.querySelectorAll('[required]');
            parentFields.forEach(field => {
              field.required = isMinor;
              field.disabled = !isMinor;
            });
          }
          
          // If 18+ and on first step, auto-advance
          if (!isMinor && currentStep === 0) {
            debugEl.textContent += ' | Auto-advancing to education section';
            console.log('User is 18+, auto-advancing past parent/guardian section');
            
            // Use the Next button's click handler to ensure consistent behavior
            setTimeout(() => {
              // Find and click the next button twice (to skip parent section)
              const nextButtons = document.querySelectorAll('.next-step');
              if (nextButtons.length > 0) {
                // First click - goes to parent section (step 2)
                nextButtons[0].click();
                // Second click - goes to education section (step 3)
                setTimeout(() => {
                  nextButtons[0].click();
                }, 100);
              }
            }, 300);
          }
        } catch (error) {
          console.error('Error in checkAgeAndProceed:', error);
        }
      }

      // Initialize Select2 for university selection (if not already initialized)
      if ($('.select2').hasClass('select2-hidden-accessible') === false) {
        $('.select2').select2({
          maximumSelectionLength: 3,
          placeholder: 'Search and select up to 3 institutions',
          allowClear: true,
          width: '100%',
          dropdownParent: $('#step-3')
        });
      }

      // Initialize Flatpickr for date of birth
      flatpickr(".datepicker", {
        dateFormat: "Y-m-d",
        maxDate: "today",
        onChange: function(selectedDates, dateStr, instance) {
          checkMinorStatus(dateStr);
        }
      });

      // Toggle other gender input
      document.querySelectorAll('input[name="gender"]').forEach(radio => {
        radio.addEventListener('change', function() {
          const otherGenderContainer = document.getElementById('otherGenderContainer');
          if (this.value === 'other') {
            otherGenderContainer.classList.remove('hidden');
            document.getElementById('otherGender').required = true;
          } else {
            otherGenderContainer.classList.add('hidden');
            document.getElementById('otherGender').required = false;
            document.getElementById('otherGender').value = '';
          }
        });
      });

      // Auto-fill date of birth from ID number
      document.getElementById('idNumber')?.addEventListener('blur', function() {
        const idNumber = this.value.trim();
        if (idNumber.length === 13 && /^\d+$/.test(idNumber)) {
          // Extract date parts from ID number (YYMMDD)
          const year = idNumber.substring(0, 2);
          const month = idNumber.substring(2, 4);
          const day = idNumber.substring(4, 6);
          
          // Determine full year (assuming 1900s for IDs starting with 00-21, 2000s for 22-99)
          const currentYear = new Date().getFullYear();
          const currentShortYear = currentYear % 100;
          const fullYear = (parseInt(year) <= currentShortYear) ? 
            `20${year.padStart(2, '0')}` : `19${year.padStart(2, '0')}`;
          
          // Format as YYYY-MM-DD for date input
          const formattedDate = `${fullYear}-${month}-${day}`;
          
          // Set the date and trigger change event
          const dobInput = document.getElementById('dob');
          if (dobInput) {
            dobInput._flatpickr.setDate(formattedDate);
            checkMinorStatus(formattedDate);
          }
        }
      });

      // Check if applicant is a minor based on date of birth
      function checkMinorStatus(dob) {
        if (!dob) {
          console.log('No DOB provided, assuming adult');
          return false;
        }
        
        try {
          const birthDate = new Date(dob);
          const today = new Date();
          
          // Check if date is valid
          if (isNaN(birthDate.getTime())) {
            console.error('Invalid date format:', dob);
            return false;
          }
          
          let age = today.getFullYear() - birthDate.getFullYear();
          const monthDiff = today.getMonth() - birthDate.getMonth();
          
          if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
          }
          
          console.log(`Age calculated: ${age} years old`);
          return age < 18;
        } catch (error) {
          console.error('Error calculating age:', error);
          return false; // Default to not minor on error
        }
      }
      
      // Toggle parent/guardian section based on age
      function toggleParentGuardianSection() {
        const dobInput = document.getElementById('dateOfBirth');
        const parentSection = document.getElementById('step-2');
        
        if (dobInput && dobInput.value) {
          const isMinor = checkMinorStatus(dobInput.value);
          
          if (!isMinor) {
            // If not a minor, hide the parent section
            if (parentSection) parentSection.style.display = 'none';
            // Make parent/guardian fields not required
            const parentFields = parentSection ? parentSection.querySelectorAll('[required]') : [];
            parentFields.forEach(field => field.required = false);
            
            // If we're currently on step 2 (parent section), skip to step 3
            if (currentStep === 1) {
              currentStep = 2; // Skip to education details
              steps.forEach((step, index) => {
                step.classList.toggle('active', index === currentStep);
              });
              updateProgressBar();
            }
          } else {
            // If minor, show the parent section and make fields required
            if (parentSection) {
              parentSection.style.display = 'block';
              const parentFields = parentSection.querySelectorAll('[required]');
              parentFields.forEach(field => field.required = true);
            }
          }
        }
      }

      // Update progress bar
      function updateProgressBar() {
        const progressBar = document.querySelector('.step-indicator-progress');
        const stepDots = document.querySelectorAll('.step');
        const totalSteps = steps.length;
        
        // Calculate progress based on current step and whether parent section is visible
        let visibleSteps = totalSteps;
        const parentSection = document.getElementById('step-2');
        if (parentSection && window.getComputedStyle(parentSection).display === 'none') {
          visibleSteps--;
          
          // If we're on a step after the hidden parent section, adjust the visual step
          let adjustedStep = currentStep;
          if (currentStep > 1) {
            adjustedStep = currentStep - 1;
          }
          
          // Update progress
          const progress = ((adjustedStep + 1) / visibleSteps) * 100;
          if (progressBar) {
            progressBar.style.width = `${progress}%`;
          }
        } else {
          // Normal progress calculation when parent section is visible
          const progress = ((currentStep + 1) / visibleSteps) * 100;
          if (progressBar) {
            progressBar.style.width = `${progress}%`;
          }
        }
        
        // Update step indicators
        stepDots.forEach((dot, index) => {
          // Skip the parent section dot if it's hidden
          const isParentSection = index === 1; // Assuming step 2 is the parent section
          const parentHidden = parentSection && window.getComputedStyle(parentSection).display === 'none';
          
          if (isParentSection && parentHidden) {
            dot.style.display = 'none';
            return;
          } else if (isParentSection) {
            dot.style.display = 'flex';
          }
          
          // Adjust index if we've hidden previous steps
          let adjustedIndex = index;
          if (index > 1 && parentHidden) {
            adjustedIndex = index - 1;
          }
          
          if (adjustedIndex < currentStep) {
            dot.classList.add('completed');
            dot.classList.remove('active');
          } else if (adjustedIndex === currentStep) {
            dot.classList.add('active');
            dot.classList.remove('completed');
          } else {
            dot.classList.remove('active', 'completed');
          }
        });
      }

      // Validate current step before proceeding
      function validateStep(step) {
        const currentFormStep = steps[step];
        const inputs = currentFormStep.querySelectorAll('input[required], select[required], textarea[required]');
        let isValid = true;
        
        // No file upload validation needed since documents are handled separately
        
        // Standard validation for other fields
        inputs.forEach(input => {
          if (input.type === 'checkbox' && !input.checked) {
            isValid = false;
            showError(input, 'This field is required');
          } else if (!input.value.trim() && input.type !== 'file') {
            isValid = false;
            showError(input, 'This field is required');
          } else if (input.type === 'email' && !isValidEmail(input.value)) {
            isValid = false;
            showError(input, 'Please enter a valid email address');
          } else if (input.type === 'tel' && !isValidPhone(input.value)) {
            isValid = false;
            showError(input, 'Please enter a valid phone number');
          } else if (input.id === 'idNumber' && !isValidID(input.value)) {
            isValid = false;
            showError(input, 'Please enter a valid 13-digit ID number');
          } else if (input.id === 'postalCode' && !isValidPostalCode(input.value)) {
            isValid = false;
            showError(input, 'Please enter a valid 4-digit postal code');
          } else {
            clearError(input);
          }
        });
        
        return isValid;
      }

      // Show error message
      function showError(input, message) {
        let formGroup = input.closest('.form-group') || input.closest('.row') || input.closest('.file-upload') || input.closest('.custom-checkbox');
        if (!formGroup) {
          formGroup = input.parentElement;
        }
        
        let errorElement = formGroup.querySelector('.error-message');
        
        if (!errorElement) {
          errorElement = document.createElement('p');
          errorElement.className = 'mt-1 text-sm text-red-600 error-message';
          formGroup.appendChild(errorElement);
        }
        
        errorElement.textContent = message;
        input.classList.add('border-red-500');
      }

      // Clear error message
      function clearError(input) {
        const formGroup = input.closest('.form-group') || input.closest('.row') || input.closest('.file-upload') || input.closest('.custom-checkbox') || input.parentElement;
        const errorElement = formGroup?.querySelector('.error-message');
        
        if (errorElement) {
          errorElement.remove();
        }
        
        input.classList.remove('border-red-500');
      }

      // Validation helper functions
      function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
      }
      
      function isValidPhone(phone) {
        const re = /^[0-9]{9}$/;
        return re.test(phone);
      }
      
      function isValidID(id) {
        const re = /^[0-9]{13}$/;
        return re.test(id);
      }
      
      function isValidPostalCode(code) {
        const re = /^[0-9]{4}$/;
        return re.test(code);
      }

      // Next button click
      nextButtons.forEach(button => {
        button.addEventListener('click', () => {
          if (validateStep(currentStep)) {
            // If moving to review page, update review section first
            if (currentStep === steps.length - 2) {
              updateReviewSection();
            }
            
            // Hide current step
            steps[currentStep].classList.remove('active');
            
            // Show next step
            currentStep++;
            steps[currentStep].classList.add('active');
            
            // Update progress bar
            updateProgressBar();
            
            // Scroll to top of form
            window.scrollTo({ top: 0, behavior: 'smooth' });
          }
        });
      });

      // Previous button click
      prevButtons.forEach(button => {
        button.addEventListener('click', () => {
          // Hide current step
          steps[currentStep].classList.remove('active');
          
          // Show previous step
          currentStep--;
          steps[currentStep].classList.add('active');
          
          // Update progress bar
          updateProgressBar();
          
          // Scroll to top of form
          window.scrollTo({ top: 0, behavior: 'smooth' });
        });
      });

      // File upload functions removed - documents are now handled separately

      // Update review section with form data
      function updateReviewSection() {
        try {
          // Personal Information
          const fullName = document.getElementById('fullName')?.value || '';
          const surname = document.getElementById('surname')?.value || '';
          document.getElementById('reviewFullName').textContent = `${fullName} ${surname}`.trim();
          
          const idNumber = document.getElementById('idNumber')?.value || 'Not provided';
          document.getElementById('reviewIdNumber').textContent = idNumber;
          
          const dob = document.getElementById('dob')?.value || 'Not provided';
          document.getElementById('reviewDob').textContent = dob;
          
          // Gender
          let genderText = 'Not specified';
          try {
            const gender = document.querySelector('input[name="gender"]:checked');
            if (gender) {
              if (gender.value === 'other') {
                const otherGender = document.getElementById('otherGender')?.value;
                genderText = otherGender || 'Other';
              } else {
                genderText = gender.value;
              }
            }
          } catch (e) {
            console.error('Error getting gender:', e);
          }
          document.getElementById('reviewGender').textContent = genderText;
          
          // Contact
          document.getElementById('reviewCellphone').textContent = `+27 ${document.getElementById('cellphone').value}`;
          document.getElementById('reviewEmail').textContent = document.getElementById('email').value;
          document.getElementById('reviewAddress').textContent = document.getElementById('address').value || 'Not provided';
          document.getElementById('reviewPostalCode').textContent = document.getElementById('postalCode').value;
          document.getElementById('reviewCountry').textContent = document.getElementById('country').value;
          document.getElementById('reviewSchool').textContent = document.getElementById('school').value || 'Not provided';
        } catch (error) {
          console.error('Error in updateReviewSection:', error);
        }
        
        // Parent/Guardian Information
        if (isMinor) {
          document.getElementById('reviewParentName').textContent = `${document.getElementById('parentFullName').value} ${document.getElementById('parentSurname').value}`;
          document.getElementById('reviewRelationship').textContent = document.getElementById('relationship').value || 'Not specified';
          document.getElementById('reviewParentId').textContent = document.getElementById('parentIdNumber').value || 'Not provided';
          document.getElementById('reviewParentCell').textContent = document.getElementById('parentCellphone').value ? `+27 ${document.getElementById('parentCellphone').value}` : 'Not provided';
          document.getElementById('reviewParentEmail').textContent = document.getElementById('parentEmail').value || 'Not provided';
        }
        
        // Education
        const selectedInstitutions = Array.from(document.querySelectorAll('#universities option:checked')).map(opt => opt.value);
        const institutionsList = document.getElementById('reviewInstitutions');
        institutionsList.innerHTML = '';
        
        if (selectedInstitutions.length > 0) {
          selectedInstitutions.forEach(institution => {
            const li = document.createElement('li');
            li.textContent = institution;
            institutionsList.appendChild(li);
          });
        } else {
          const li = document.createElement('li');
          li.textContent = 'No institutions selected';
          li.classList.add('text-gray-500');
          institutionsList.appendChild(li);
        }
        
        document.getElementById('reviewFirstOption').textContent = document.getElementById('firstOption').value || 'Not specified';
        document.getElementById('reviewSecondOption').textContent = document.getElementById('secondOption').value || 'Not specified';
        
        const otherUniversity = document.getElementById('otherUniversity').value;
        const otherUniversityContainer = document.getElementById('otherUniversityReviewContainer');
        if (otherUniversity) {
          document.getElementById('reviewOtherUniversity').textContent = otherUniversity;
          otherUniversityContainer.style.display = 'block';
        } else {
          otherUniversityContainer.style.display = 'none';
        }
        
        // Document review section removed - documents are handled separately
      }
      
      // Form submission
      form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Validate final step
        if (!validateStep(currentStep)) {
          showToast('Please complete all required fields before submitting.', 'error');
          return;
        }
        
        const submitButton = form.querySelector('button[type="submit"]');
        const originalHTML = submitButton.innerHTML;
        
        // Show loading state
        submitButton.disabled = true;
        submitButton.innerHTML = `
          <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          Submitting...
        `;
        
        try {
          // Create FormData object
          const formData = new FormData(form);
          
          // Add selected universities to form data
          const selectedInstitutions = Array.from(document.querySelectorAll('#universities option:checked')).map(opt => opt.value);
          selectedInstitutions.forEach((institution, index) => {
            formData.append(`university${index + 1}`, institution);
          });
          
          // Send the form data to the server
          const response = await fetch('apply-handler.php', {
            method: 'POST',
            body: formData
          });
          
          const result = await response.json();
          
          if (result.success) {
            // Show success message
            showToast('Application submitted successfully!', 'success');
            
            // Reset form
            form.reset();
            
            // File upload reset code removed - documents are handled separately
            
            // Reset Select2
            $('.select2').val(null).trigger('change');
            
            // Reset to first step
            steps[currentStep].classList.remove('active');
            currentStep = 0;
            steps[currentStep].classList.add('active');
            updateProgressBar();
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            // Show confirmation message
            setTimeout(() => {
              showToast('Thank you for your application! We will contact you soon.', 'success');
            }, 1000);
          } else {
            throw new Error(result.message || 'Failed to submit application');
          }
        } catch (error) {
          console.error('Form submission error:', error);
          showToast(error.message || 'Sorry, there was an error submitting your application. Please try again.', 'error');
        } finally {
          // Reset button state
          if (submitButton) {
            submitButton.disabled = false;
            submitButton.innerHTML = originalHTML;
          }
        }
      });
      
      // Show toast notification
      function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg font-semibold text-white z-50 transform transition-transform duration-300 ${type === 'success' ? 'bg-green-500' : 'bg-red-500'}`;
        
        // Show toast
        setTimeout(() => {
          toast.classList.remove('translate-x-full');
        }, 100);
        
        // Hide toast after 5 seconds
        setTimeout(() => {
          toast.classList.add('translate-x-full');
        }, 5000);
      }
      
      // Initialize the form
      console.log('Initializing form...');
      updateProgressBar();
      
      // Check initial DOB if it exists
      const initialDob = document.getElementById('dateOfBirth')?.value;
      if (initialDob) {
        console.log('Found initial DOB:', initialDob);
        checkAgeAndProceed(initialDob);
      }
      
      // Toggle other gender input
      document.querySelectorAll('input[name="gender"]').forEach(radio => {
        radio.addEventListener('change', function() {
          const otherGenderContainer = document.getElementById('otherGenderContainer');
          if (this.value === 'other' && otherGenderContainer) {
            otherGenderContainer.classList.remove('hidden');
            otherGenderContainer.querySelector('input').required = true;
          } else if (otherGenderContainer) {
            otherGenderContainer.classList.add('hidden');
            otherGenderContainer.querySelector('input').required = false;
          }
        });
      });
      
      // Make functions available globally if needed
      window.checkAgeAndProceed = checkAgeAndProceed;
      window.checkMinorStatus = checkMinorStatus;
      
      // Initialize form
      updateReviewSection();
      
      // Add event listeners for form updates
      document.querySelectorAll('input, select, textarea').forEach(input => {
        input.addEventListener('change', updateReviewSection);
        input.addEventListener('input', updateReviewSection);
      });
    });
  })();
  </script>
</body>
</html>
