<?php
// Step 2: Parent/Guardian Details
$step2_data = $form_data['step_2'] ?? [];
?>

<h2>👨‍👩‍👧‍👦 Parent/Guardian Details</h2>
<p>Please provide your parent or guardian's information. These fields are optional but recommended for contact purposes.</p>

<div class="form-row">
    <div class="form-col">
        <label for="parent_full_name">Full Name</label>
        <input type="text" name="parent_full_name" id="parent_full_name" 
               value="<?php echo htmlspecialchars($step2_data['parent_full_name'] ?? ''); ?>" 
               placeholder="Parent/Guardian's full name">
    </div>
    <div class="form-col">
        <label for="parent_surname">Surname</label>
        <input type="text" name="parent_surname" id="parent_surname" 
               value="<?php echo htmlspecialchars($step2_data['parent_surname'] ?? ''); ?>" 
               placeholder="Parent/Guardian's surname">
    </div>
</div>

<div class="form-row">
    <div class="form-col">
        <label for="marital_status">Marital Status</label>
        <select name="marital_status" id="marital_status">
            <option value="">Select Marital Status</option>
            <option value="Single" <?php echo ($step2_data['marital_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
            <option value="Married" <?php echo ($step2_data['marital_status'] ?? '') === 'Married' ? 'selected' : ''; ?>>Married</option>
            <option value="Divorced" <?php echo ($step2_data['marital_status'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
            <option value="Widowed" <?php echo ($step2_data['marital_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
            <option value="Other" <?php echo ($step2_data['marital_status'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
        </select>
    </div>
    <div class="form-col">
        <label for="parent_id_number">ID Number</label>
        <input type="text" name="parent_id_number" id="parent_id_number" 
               value="<?php echo htmlspecialchars($step2_data['parent_id_number'] ?? ''); ?>" 
               placeholder="13-digit ID number" 
               pattern="[0-9]{13}" maxlength="13">
        <small style="color: #7f8c8d;">Optional: 13-digit South African ID number</small>
    </div>
</div>

<div class="form-row">
    <div class="form-col">
        <label for="parent_contact_number">Contact Number</label>
        <input type="tel" name="parent_contact_number" id="parent_contact_number" 
               value="<?php echo htmlspecialchars($step2_data['parent_contact_number'] ?? ''); ?>" 
               placeholder="e.g., 0821234567" 
               pattern="0[6-8][0-9]{8}">
        <small style="color: #7f8c8d;">Format: 0821234567</small>
    </div>
    <div class="form-col">
        <label for="parent_email_address">Email Address</label>
        <input type="email" name="parent_email_address" id="parent_email_address" 
               value="<?php echo htmlspecialchars($step2_data['parent_email_address'] ?? ''); ?>" 
               placeholder="parent.email@example.com">
    </div>
</div>

<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px;">
    <h3 style="color: #2c3e50; margin-bottom: 15px;">📝 Why do we need parent/guardian information?</h3>
    <ul style="color: #7f8c8d; line-height: 1.6;">
        <li>Emergency contact purposes</li>
        <li>Communication regarding your application status</li>
        <li>Financial aid and bursary applications</li>
        <li>University orientation and registration assistance</li>
    </ul>
    <p style="color: #7f8c8d; margin-top: 15px; font-style: italic;">
        <strong>Note:</strong> If you are 18 years or older, you may skip this section if you prefer to be the sole contact for your application.
    </p>
</div>

<script>
// Validation for Step 2
document.getElementById('parent_id_number').addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
    if (this.value.length > 13) {
        this.value = this.value.slice(0, 13);
    }
});

document.getElementById('parent_contact_number').addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
    if (this.value.length > 10) {
        this.value = this.value.slice(0, 10);
    }
});

// Email validation
document.getElementById('parent_email_address').addEventListener('blur', function() {
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
document.getElementById('parent_id_number').addEventListener('blur', function() {
    const idNumber = this.value;
    
    if (idNumber && idNumber.length !== 13) {
        this.style.borderColor = '#e74c3c';
        alert('ID number must be exactly 13 digits.');
    } else {
        this.style.borderColor = '#ecf0f1';
    }
});
</script>