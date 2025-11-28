<?php
// partials/application-form.php
if (!function_exists('e')) {
    function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$formMode = $formMode ?? 'create';
$values = is_array($values ?? null) ? $values : [];
$universities = $universities ?? [];
$university_choices = $university_choices ?? [];

// Form action
$action = ($formMode === 'edit') ? 'student-apply.php' : 'apply.php';
$csrf_token = $csrf_token ?? ($_SESSION[CSRF_TOKEN_NAME] ?? '');
?>

<form id="application-form" method="post" action="<?php echo e($action); ?>" enctype="multipart/form-data">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo e($csrf_token); ?>">
    
    <?php if ($formMode === 'edit' && isset($values['id'])): ?>
        <input type="hidden" name="application_id" value="<?php echo e($values['id']); ?>">
    <?php endif; ?>

    <!-- Personal Information -->
    <div class="form-section">
        <h2 class="section-title">
            <i class="fas fa-user"></i> Personal Information
        </h2>
        <div class="form-grid">
            <div class="form-group">
                <label for="full_name" class="form-label required">First Name</label>
                <input type="text" id="full_name" name="full_name" class="form-input" 
                       value="<?php echo e($values['full_name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="surname" class="form-label required">Surname</label>
                <input type="text" id="surname" name="surname" class="form-input" 
                       value="<?php echo e($values['surname'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="email_address" class="form-label required">Email Address</label>
                <input type="email" id="email_address" name="email_address" class="form-input" 
                       value="<?php echo e($values['email_address'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="cellphone_number" class="form-label required">Cellphone Number</label>
                <input type="tel" id="cellphone_number" name="cellphone_number" class="form-input" 
                       value="<?php echo e($values['cellphone_number'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="date_of_birth" class="form-label">Date of Birth</label>
                <input type="date" id="date_of_birth" name="date_of_birth" class="form-input" 
                       value="<?php echo e($values['date_of_birth'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="id_number" class="form-label">ID Number</label>
                <input type="text" id="id_number" name="id_number" class="form-input" 
                       value="<?php echo e($values['id_number'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="gender" class="form-label">Gender</label>
                <select id="gender" name="gender" class="form-select">
                    <option value="">Select Gender</option>
                    <option value="male" <?php echo ($values['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                    <option value="female" <?php echo ($values['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                    <option value="other" <?php echo ($values['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="home_language" class="form-label">Home Language</label>
                <input type="text" id="home_language" name="home_language" class="form-input" 
                       value="<?php echo e($values['home_language'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="nationality" class="form-label">Nationality</label>
                <input type="text" id="nationality" name="nationality" class="form-input" 
                       value="<?php echo e($values['nationality'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="province" class="form-label">Province</label>
                <select id="province" name="province" class="form-select">
                    <option value="">Select Province</option>
                    <option value="eastern_cape" <?php echo ($values['province'] ?? '') === 'eastern_cape' ? 'selected' : ''; ?>>Eastern Cape</option>
                    <option value="free_state" <?php echo ($values['province'] ?? '') === 'free_state' ? 'selected' : ''; ?>>Free State</option>
                    <option value="gauteng" <?php echo ($values['province'] ?? '') === 'gauteng' ? 'selected' : ''; ?>>Gauteng</option>
                    <option value="kwazulu_natal" <?php echo ($values['province'] ?? '') === 'kwazulu_natal' ? 'selected' : ''; ?>>KwaZulu-Natal</option>
                    <option value="limpopo" <?php echo ($values['province'] ?? '') === 'limpopo' ? 'selected' : ''; ?>>Limpopo</option>
                    <option value="mpumalanga" <?php echo ($values['province'] ?? '') === 'mpumalanga' ? 'selected' : ''; ?>>Mpumalanga</option>
                    <option value="northern_cape" <?php echo ($values['province'] ?? '') === 'northern_cape' ? 'selected' : ''; ?>>Northern Cape</option>
                    <option value="north_west" <?php echo ($values['province'] ?? '') === 'north_west' ? 'selected' : ''; ?>>North West</option>
                    <option value="western_cape" <?php echo ($values['province'] ?? '') === 'western_cape' ? 'selected' : ''; ?>>Western Cape</option>
                </select>
            </div>
            <div class="form-group full-width">
                <label for="postal_address" class="form-label">Postal Address</label>
                <textarea id="postal_address" name="postal_address" class="form-textarea"><?php echo e($values['postal_address'] ?? ''); ?></textarea>
            </div>
            <div class="form-group full-width">
                <label for="physical_address" class="form-label">Physical Address</label>
                <textarea id="physical_address" name="physical_address" class="form-textarea"><?php echo e($values['physical_address'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="city" class="form-label">City</label>
                <input type="text" id="city" name="city" class="form-input" 
                       value="<?php echo e($values['city'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="postal_code" class="form-label">Postal Code</label>
                <input type="text" id="postal_code" name="postal_code" class="form-input" 
                       value="<?php echo e($values['postal_code'] ?? ''); ?>">
            </div>
        </div>
    </div>

    <!-- Parent/Guardian Information -->
    <div class="form-section">
        <h2 class="section-title">
            <i class="fas fa-users"></i> Parent/Guardian Information
        </h2>
        <div class="form-grid">
            <div class="form-group">
                <label for="parent_full_name" class="form-label">First Name</label>
                <input type="text" id="parent_full_name" name="parent_full_name" class="form-input" 
                       value="<?php echo e($values['full_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="parent_surname" class="form-label">Surname</label>
                <input type="text" id="parent_surname" name="parent_surname" class="form-input" 
                       value="<?php echo e($values['surname'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="parent_relationship" class="form-label">Relationship</label>
                <select id="parent_relationship" name="parent_relationship" class="form-select">
                    <option value="">Select Relationship</option>
                    <option value="parent" <?php echo ($values['relationship'] ?? '') === 'parent' ? 'selected' : ''; ?>>Parent</option>
                    <option value="guardian" <?php echo ($values['relationship'] ?? '') === 'guardian' ? 'selected' : ''; ?>>Guardian</option>
                    <option value="grandparent" <?php echo ($values['relationship'] ?? '') === 'grandparent' ? 'selected' : ''; ?>>Grandparent</option>
                    <option value="other" <?php echo ($values['relationship'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="parent_cellphone" class="form-label">Cellphone Number</label>
                <input type="tel" id="parent_cellphone" name="parent_cellphone" class="form-input" 
                       value="<?php echo e($values['cellphone_number'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="parent_email" class="form-label">Email Address</label>
                <input type="email" id="parent_email" name="parent_email" class="form-input" 
                       value="<?php echo e($values['email_address'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="parent_occupation" class="form-label">Occupation</label>
                <input type="text" id="parent_occupation" name="parent_occupation" class="form-input" 
                       value="<?php echo e($values['occupation'] ?? ''); ?>">
            </div>
            <div class="form-group full-width">
                <label for="parent_employer" class="form-label">Employer</label>
                <input type="text" id="parent_employer" name="parent_employer" class="form-input" 
                       value="<?php echo e($values['employer'] ?? ''); ?>">
            </div>
        </div>
    </div>

    <!-- University Choices -->
    <div class="form-section">
        <h2 class="section-title">
            <i class="fas fa-university"></i> University Choices
        </h2>
        <div id="universityChoices">
            <?php foreach ($university_choices as $index => $choice): ?>
            <div class="university-choice">
                <div class="choice-header">
                    <span class="choice-number">Choice <?php echo $index + 1; ?></span>
                    <?php if ($index > 0): ?>
                    <button type="button" class="remove-choice" onclick="removeChoice(this)">Remove</button>
                    <?php endif; ?>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">University</label>
                        <select name="university_choices[<?php echo $index; ?>][university_id]" class="form-select">
                            <option value="">Select University</option>
                            <?php foreach ($universities as $university): ?>
                            <option value="<?php echo $university['id']; ?>" 
                                    <?php echo $choice['university_id'] == $university['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($university['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Course/Program</label>
                        <input type="text" name="university_choices[<?php echo $index; ?>][course]" class="form-input" 
                               value="<?php echo htmlspecialchars($choice['course'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($university_choices)): ?>
            <div class="university-choice">
                <div class="choice-header">
                    <span class="choice-number">Choice 1</span>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">University</label>
                        <select name="university_choices[0][university_id]" class="form-select">
                            <option value="">Select University</option>
                            <?php foreach ($universities as $university): ?>
                            <option value="<?php echo $university['id']; ?>">
                                <?php echo htmlspecialchars($university['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Course/Program</label>
                        <input type="text" name="university_choices[0][course]" class="form-input">
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <button type="button" class="add-choice" onclick="addChoice()">
            <i class="fas fa-plus"></i> Add Another Choice
        </button>
    </div>

    <!-- Supporting Documents -->
    <div class="form-section">
        <h2 class="section-title">
            <i class="fas fa-file-upload"></i> Supporting Documents
        </h2>
        <div class="form-grid">
            <div class="form-group full-width">
                <label for="supporting_documents" class="form-label">Upload Documents (ID, Academic Records, etc.)</label>
                <input type="file" id="supporting_documents" name="supporting_documents[]" class="form-input" multiple>
                <small class="form-text">You can upload multiple files. Accepted formats: PDF, JPG, PNG</small>
            </div>
        </div>
    </div>

    <!-- Declaration -->
    <div class="form-section">
        <h2 class="section-title">
            <i class="fas fa-check-circle"></i> Declaration
        </h2>
        <div class="form-group">
            <div class="form-check">
                <input type="checkbox" id="declaration" name="declaration" class="form-check-input" required>
                <label for="declaration" class="form-label required">
                    I declare that the information provided in this application is true and complete to the best of my knowledge.
                </label>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <a href="student-dashboard.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancel
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-<?php echo $formMode === 'edit' ? 'save' : 'paper-plane'; ?>"></i> 
            <?php echo $formMode === 'edit' ? 'Update Application' : 'Submit Application'; ?>
        </button>
    </div>
</form>

<script>
// University choices management
let choiceCount = <?php echo max(1, count($university_choices)); ?>;
const universities = <?php echo json_encode($universities); ?>;

function addChoice() {
    if (choiceCount >= 5) {
        alert('Maximum 5 university choices allowed.');
        return;
    }

    const container = document.getElementById('universityChoices');
    const choiceDiv = document.createElement('div');
    choiceDiv.className = 'university-choice';
    
    choiceDiv.innerHTML = `
        <div class="choice-header">
            <span class="choice-number">Choice ${choiceCount + 1}</span>
            <button type="button" class="remove-choice" onclick="removeChoice(this)">Remove</button>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">University</label>
                <select name="university_choices[${choiceCount}][university_id]" class="form-select">
                    <option value="">Select University</option>
                    ${universities.map(uni => `<option value="${uni.id}">${uni.name}</option>`).join('')}
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Course/Program</label>
                <input type="text" name="university_choices[${choiceCount}][course]" class="form-input">
            </div>
        </div>
    `;
    
    container.appendChild(choiceDiv);
    choiceCount++;
    updateChoiceNumbers();
}

function removeChoice(button) {
    button.closest('.university-choice').remove();
    choiceCount--;
    updateChoiceNumbers();
}

function updateChoiceNumbers() {
    const choices = document.querySelectorAll('.university-choice');
    choices.forEach((choice, index) => {
        choice.querySelector('.choice-number').textContent = `Choice ${index + 1}`;
        
        // Update input names
        const selects = choice.querySelectorAll('select');
        const inputs = choice.querySelectorAll('input');
        
        selects.forEach(select => {
            const name = select.name.replace(/\[\d+\]/, `[${index}]`);
            select.name = name;
        });
        
        inputs.forEach(input => {
            const name = input.name.replace(/\[\d+\]/, `[${index}]`);
            input.name = name;
        });
    });
}

// Form validation
document.getElementById('application-form')?.addEventListener('submit', function(e) {
    const requiredFields = ['full_name', 'surname', 'email_address', 'cellphone_number'];
    let isValid = true;
    
    requiredFields.forEach(field => {
        const input = document.getElementById(field);
        if (!input.value.trim()) {
            input.style.borderColor = 'var(--red-500)';
            isValid = false;
        } else {
            input.style.borderColor = 'var(--gray-300)';
        }
    });
    
    // Check declaration
    const declaration = document.getElementById('declaration');
    if (!declaration.checked) {
        declaration.style.outline = '2px solid var(--red-500)';
        isValid = false;
    } else {
        declaration.style.outline = '';
    }
    
    if (!isValid) {
        e.preventDefault();
        alert('Please fill in all required fields and accept the declaration.');
    }
});
</script>