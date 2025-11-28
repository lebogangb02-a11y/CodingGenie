<?php
// Step 3: University Application
$step3_data = $form_data['step_3'] ?? [];

// Get universities from database or use default list
$universities = [
    'UCT' => 'University of Cape Town',
    'Wits' => 'University of the Witwatersrand',
    'Stellenbosch' => 'Stellenbosch University',
    'UP' => 'University of Pretoria',
    'UJ' => 'University of Johannesburg',
    'UKZN' => 'University of KwaZulu-Natal',
    'NWU' => 'North-West University',
    'UNISA' => 'University of South Africa',
    'TUT' => 'Tshwane University of Technology',
    'UFS' => 'University of the Free State',
    'Other' => 'Other (specify in comments)'
];
?>

<h2>🎓 University Application</h2>
<p>Select up to 3 universities you wish to apply to and specify your course preferences.</p>

<div class="form-group">
    <label>University Choices (Select up to 3)</label>
    <p style="color: #7f8c8d; margin-bottom: 15px;">Choose your preferred universities in order of preference:</p>
    
    <div class="form-row">
        <div class="form-col">
            <label for="university_1">First Choice University</label>
            <select name="university_1" id="university_1">
                <option value="">Select First Choice</option>
                <?php foreach ($universities as $code => $name): ?>
                    <option value="<?php echo $code; ?>" <?php echo ($step3_data['university_1'] ?? '') === $code ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    
    <div class="form-row">
        <div class="form-col">
            <label for="university_2">Second Choice University (Optional)</label>
            <select name="university_2" id="university_2">
                <option value="">Select Second Choice</option>
                <?php foreach ($universities as $code => $name): ?>
                    <option value="<?php echo $code; ?>" <?php echo ($step3_data['university_2'] ?? '') === $code ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    
    <div class="form-row">
        <div class="form-col">
            <label for="university_3">Third Choice University (Optional)</label>
            <select name="university_3" id="university_3">
                <option value="">Select Third Choice</option>
                <?php foreach ($universities as $code => $name): ?>
                    <option value="<?php echo $code; ?>" <?php echo ($step3_data['university_3'] ?? '') === $code ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<div class="form-row">
    <div class="form-col">
        <label for="course_first_choice">Course You Wish to Apply For - First Option</label>
        <input type="text" name="course_first_choice" id="course_first_choice" 
               value="<?php echo htmlspecialchars($step3_data['course_first_choice'] ?? ''); ?>" 
               placeholder="e.g., Bachelor of Science in Computer Science">
        <small style="color: #7f8c8d;">Enter the full name of your preferred course</small>
    </div>
</div>

<div class="form-row">
    <div class="form-col">
        <label for="course_second_choice">Course You Wish to Apply For - Second Option</label>
        <input type="text" name="course_second_choice" id="course_second_choice" 
               value="<?php echo htmlspecialchars($step3_data['course_second_choice'] ?? ''); ?>" 
               placeholder="e.g., Bachelor of Commerce in Accounting">
        <small style="color: #7f8c8d;">Enter an alternative course option</small>
    </div>
</div>

<div class="form-group">
    <label for="additional_comments">Additional Comments</label>
    <textarea name="additional_comments" id="additional_comments" 
              placeholder="Any additional university choices, special requirements, or comments about your application..."><?php echo htmlspecialchars($step3_data['additional_comments'] ?? ''); ?></textarea>
    <small style="color: #7f8c8d;">Use this space to mention any other universities not listed above or special circumstances</small>
</div>

<div style="background: #e8f5e8; padding: 20px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #27ae60;">
    <h3 style="color: #27ae60; margin-bottom: 15px;">💡 Application Tips</h3>
    <ul style="color: #2c3e50; line-height: 1.6;">
        <li><strong>Research thoroughly:</strong> Make sure you understand the admission requirements for each university and course</li>
        <li><strong>Have backup options:</strong> Select universities with different admission requirements to increase your chances</li>
        <li><strong>Course prerequisites:</strong> Ensure you meet the subject and grade requirements for your chosen courses</li>
        <li><strong>Application deadlines:</strong> Each university has different application deadlines - check them carefully</li>
        <li><strong>Financial planning:</strong> Consider tuition fees, accommodation, and living costs for each university</li>
    </ul>
</div>

<div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #ffc107;">
    <h3 style="color: #856404; margin-bottom: 15px;">⚠️ Important Notes</h3>
    <ul style="color: #856404; line-height: 1.6;">
        <li>This application portal helps you organize your information, but you must still apply directly to each university</li>
        <li>Each university has its own application process and requirements</li>
        <li>Use this portal to keep track of your applications and required documents</li>
        <li>We'll provide you with a reference number to track your progress</li>
    </ul>
</div>

<script>
// Prevent selecting the same university multiple times
function validateUniversitySelections() {
    const selects = ['university_1', 'university_2', 'university_3'];
    const values = selects.map(id => document.getElementById(id).value).filter(v => v);
    
    // Check for duplicates
    const hasDuplicates = values.length !== new Set(values).size;
    
    if (hasDuplicates) {
        alert('Please select different universities for each choice.');
        return false;
    }
    
    return true;
}

// Add event listeners to university selects
document.getElementById('university_1').addEventListener('change', validateUniversitySelections);
document.getElementById('university_2').addEventListener('change', validateUniversitySelections);
document.getElementById('university_3').addEventListener('change', validateUniversitySelections);

// Show/hide other university input
function toggleOtherUniversityInput() {
    const selects = ['university_1', 'university_2', 'university_3'];
    let showOtherInput = false;
    
    selects.forEach(selectId => {
        const select = document.getElementById(selectId);
        if (select.value === 'Other') {
            showOtherInput = true;
        }
    });
    
    // You can add logic here to show/hide additional input for "Other" university
}

document.getElementById('university_1').addEventListener('change', toggleOtherUniversityInput);
document.getElementById('university_2').addEventListener('change', toggleOtherUniversityInput);
document.getElementById('university_3').addEventListener('change', toggleOtherUniversityInput);

// Course suggestions based on popular choices
const popularCourses = [
    'Bachelor of Science in Computer Science',
    'Bachelor of Commerce in Accounting',
    'Bachelor of Engineering in Civil Engineering',
    'Bachelor of Medicine and Bachelor of Surgery (MBChB)',
    'Bachelor of Laws (LLB)',
    'Bachelor of Education',
    'Bachelor of Science in Nursing',
    'Bachelor of Commerce in Business Management',
    'Bachelor of Arts in Psychology',
    'Bachelor of Science in Mathematics'
];

// Add autocomplete functionality
function addAutocomplete(inputId) {
    const input = document.getElementById(inputId);
    
    input.addEventListener('input', function() {
        const value = this.value.toLowerCase();
        
        // Simple autocomplete logic can be added here
        // For now, we'll just provide the placeholder suggestions
    });
}

addAutocomplete('course_first_choice');
addAutocomplete('course_second_choice');
</script>