<?php
// Step 1: Personal Information
$step1_data = $form_data['step_1'] ?? [];
?>

<h2>📋 Personal Information</h2>
<p>Please provide your personal details. Fields marked with <span class="required">*</span> are required.</p>

<div class="form-row">
    <div class="form-col">
        <label for="gender">Gender <span class="required">*</span></label>
        <select name="gender" id="gender" required>
            <option value="">Select Gender</option>
            <option value="Male" <?php echo ($step1_data['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
            <option value="Female" <?php echo ($step1_data['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
        </select>
    </div>
</div>

<div class="form-row">
    <div class="form-col">
        <label for="full_name">Full Name <span class="required">*</span></label>
        <input type="text" name="full_name" id="full_name" 
               value="<?php echo htmlspecialchars($step1_data['full_name'] ?? ''); ?>" 
               placeholder="Enter your full name" required>
    </div>
    <div class="form-col">
        <label for="surname">Surname <span class="required">*</span></label>
        <input type="text" name="surname" id="surname" 
               value="<?php echo htmlspecialchars($step1_data['surname'] ?? ''); ?>" 
               placeholder="Enter your surname" required>
    </div>
</div>

<div class="form-row">
    <div class="form-col">
        <label for="id_number">ID Number <span class="required">*</span></label>
        <input type="text" name="id_number" id="id_number" 
               value="<?php echo htmlspecialchars($step1_data['id_number'] ?? ''); ?>" 
               placeholder="Enter your 13-digit ID number" 
               pattern="[0-9]{13}" maxlength="13" required>
        <small style="color: #7f8c8d;">Enter your 13-digit South African ID number</small>
    </div>
    <div class="form-col">
        <label for="cellphone_number">Cellphone Number <span class="required">*</span></label>
        <input type="tel" name="cellphone_number" id="cellphone_number" 
               value="<?php echo htmlspecialchars($step1_data['cellphone_number'] ?? ''); ?>" 
               placeholder="e.g., 0821234567" 
               pattern="0[6-8][0-9]{8}" required>
        <small style="color: #7f8c8d;">Format: 0821234567</small>
    </div>
</div>

<div class="form-row">
    <div class="form-col">
        <label for="date_of_birth">Date of Birth <span class="required">*</span></label>
        <input type="date" name="date_of_birth" id="date_of_birth" 
               value="<?php echo htmlspecialchars($step1_data['date_of_birth'] ?? ''); ?>" 
               max="<?php echo date('Y-m-d', strtotime('-16 years')); ?>" required>
    </div>
    <div class="form-col">
        <label for="email_address">Email Address <span class="required">*</span></label>
        <input type="email" name="email_address" id="email_address" 
               value="<?php echo htmlspecialchars($step1_data['email_address'] ?? ''); ?>" 
               placeholder="your.email@example.com" required>
    </div>
</div>

<div class="form-group">
    <label for="physical_address">Physical Address</label>
    <textarea name="physical_address" id="physical_address" 
              placeholder="Enter your complete physical address"><?php echo htmlspecialchars($step1_data['physical_address'] ?? ''); ?></textarea>
</div>

<div class="form-row">
    <div class="form-col">
        <label for="postal_code">Postal Code <span class="required">*</span></label>
        <input type="text" name="postal_code" id="postal_code" 
               value="<?php echo htmlspecialchars($step1_data['postal_code'] ?? ''); ?>" 
               placeholder="e.g., 2000" 
               pattern="[0-9]{4}" maxlength="4" required>
    </div>
    <div class="form-col">
        <label for="country_of_residence">Country of Residence <span class="required">*</span></label>
        <select name="country_of_residence" id="country_of_residence" required>
            <option value="">Select Country</option>
            <option value="South Africa" <?php echo ($step1_data['country_of_residence'] ?? '') === 'South Africa' ? 'selected' : ''; ?>>South Africa</option>
            <option value="Botswana" <?php echo ($step1_data['country_of_residence'] ?? '') === 'Botswana' ? 'selected' : ''; ?>>Botswana</option>
            <option value="Lesotho" <?php echo ($step1_data['country_of_residence'] ?? '') === 'Lesotho' ? 'selected' : ''; ?>>Lesotho</option>
            <option value="Namibia" <?php echo ($step1_data['country_of_residence'] ?? '') === 'Namibia' ? 'selected' : ''; ?>>Namibia</option>
            <option value="Swaziland" <?php echo ($step1_data['country_of_residence'] ?? '') === 'Swaziland' ? 'selected' : ''; ?>>Swaziland</option>
            <option value="Zimbabwe" <?php echo ($step1_data['country_of_residence'] ?? '') === 'Zimbabwe' ? 'selected' : ''; ?>>Zimbabwe</option>
            <option value="Other" <?php echo ($step1_data['country_of_residence'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
        </select>
    </div>
</div>

<script>
// Additional validation for Step 1
document.getElementById('id_number').addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
    if (this.value.length > 13) {
        this.value = this.value.slice(0, 13);
    }
});

document.getElementById('cellphone_number').addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
    if (this.value.length > 10) {
        this.value = this.value.slice(0, 10);
    }
});

document.getElementById('postal_code').addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
    if (this.value.length > 4) {
        this.value = this.value.slice(0, 4);
    }
});

// Email validation
document.getElementById('email_address').addEventListener('blur', function() {
    const email = this.value;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (email && !emailRegex.test(email)) {
        this.style.borderColor = '#e74c3c';
        alert('Please enter a valid email address.');
    } else {
        this.style.borderColor = '#ecf0f1';
    }
});

// ID number validation
document.getElementById('id_number').addEventListener('blur', function() {
    const idNumber = this.value;
    
    if (idNumber && idNumber.length !== 13) {
        this.style.borderColor = '#e74c3c';
        alert('ID number must be exactly 13 digits.');
    } else {
        this.style.borderColor = '#ecf0f1';
    }
});
</script>