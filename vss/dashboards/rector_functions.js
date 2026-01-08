// Complete JavaScript functions for rector dashboard buttons

// Global variables
let currentStudentGRN = '';
let currentStudentName = '';
let currentStudentEmail = '';

// View Student Credentials
window.viewStudentCredentials = function(grn, name, email) {
    window.currentStudentGRN = grn;
    window.currentStudentName = name;
    window.currentStudentEmail = email || '';
    
    document.getElementById('credentialStudentName').value = name;
    document.getElementById('credentialGRN').value = grn;
    document.getElementById('credentialPassword').value = 'Loading...';
    
    fetch('../handlers/get_student_password.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'grn=' + encodeURIComponent(grn)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('credentialPassword').value = data.password;
        } else {
            document.getElementById('credentialPassword').value = 'Error: ' + data.message;
        }
    })
    .catch(error => {
        document.getElementById('credentialPassword').value = 'Error loading password';
    });
    
    new bootstrap.Modal(document.getElementById('viewCredentialsModal')).show();
}

// Generate New Password
window.generateNewPassword = function() {
    if (!confirm('Generate a new password for this student? The old password will be replaced.')) return;
    
    document.getElementById('credentialPassword').value = 'Generating...';
    
    fetch('../handlers/get_student_password.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'grn=' + encodeURIComponent(window.currentStudentGRN) + '&generate_new=1'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('credentialPassword').value = data.password;
            alert('✅ New password generated: ' + data.password);
        } else {
            alert('❌ Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('❌ Error generating password');
    });
}

// Send Credentials Email
window.sendCredentialsEmail = function() {
    const grn = document.getElementById('credentialGRN').value;
    const password = document.getElementById('credentialPassword').value;
    
    if (!window.currentStudentEmail) {
        alert('❌ Student email not available');
        return;
    }
    
    if (password === 'Loading...' || password === 'Generating...') {
        alert('⚠️ Please wait for password to load');
        return;
    }
    
    const subject = 'Your VSS Hostel Login Credentials';
    const message = `Dear ${window.currentStudentName},\n\nYour login credentials for VSS Hostel Management System:\n\nUsername (GRN): ${grn}\nPassword: ${password}\n\nPlease login and change your password after first login.\n\nLogin URL: ${window.location.origin}/vss/auth/login.php`;
    
    const formData = new FormData();
    formData.append('action', 'send_custom_email');
    formData.append('student_email', window.currentStudentEmail);
    formData.append('student_name', window.currentStudentName);
    formData.append('subject', subject);
    formData.append('message', message);
    
    fetch('../handlers/send_custom_email.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Credentials sent to ' + currentStudentEmail);
        } else {
            alert('❌ Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('❌ Error sending email');
    });
}

// Edit Student
window.editStudent = function(id, name, email, contact, course, year, room) {
    console.log('Edit Student clicked:', id, name);
    document.getElementById('editStudentId').value = id;
    document.getElementById('editStudentName').value = name;
    document.getElementById('editStudentEmail').value = email || '';
    document.getElementById('editStudentContact').value = contact || '';
    document.getElementById('editStudentCourse').value = course || '';
    document.getElementById('editStudentYear').value = year || 1;
    document.getElementById('editStudentRoom').value = room || '';
    new bootstrap.Modal(document.getElementById('editStudentModal')).show();
}

// Open Email Modal
window.openEmailModal = function(studentName, studentEmail) {
    console.log('Send Email clicked:', studentName, studentEmail);
    document.getElementById('studentEmail').value = studentEmail;
    document.getElementById('emailSubject').value = 'Message from VSS Hostel - ' + studentName;
    document.getElementById('emailMessage').value = '';
    document.getElementById('emailAttachmentLinks').value = '';
    document.getElementById('emailAttachmentLinks').style.display = 'none';
    new bootstrap.Modal(document.getElementById('sendAdmissionModal')).show();
}

// Copy to Clipboard
window.copyToClipboard = function(elementId) {
    const element = document.getElementById(elementId);
    element.select();
    document.execCommand('copy');
    
    const button = event.target.closest('button');
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check"></i>';
    button.classList.add('btn-success');
    button.classList.remove('btn-outline-secondary');
    
    setTimeout(() => {
        button.innerHTML = originalHTML;
        button.classList.remove('btn-success');
        button.classList.add('btn-outline-secondary');
    }, 2000);
}

// Toggle Links Input
window.toggleLinksInput = function(id) {
    const linksInput = document.getElementById(id);
    if (linksInput.style.display === 'none' || linksInput.style.display === '') {
        linksInput.style.display = 'block';
    } else {
        linksInput.style.display = 'none';
    }
}

// Show Selected Files
window.showSelectedFiles = function(input, listId) {
    const listDiv = document.getElementById(listId);
    listDiv.innerHTML = '';
    if (input.files.length > 0) {
        const fileList = document.createElement('div');
        fileList.className = 'alert alert-info py-2';
        fileList.innerHTML = '<small><i class="fas fa-paperclip me-1"></i><strong>' + input.files.length + ' file(s) selected:</strong></small>';
        const ul = document.createElement('ul');
        ul.className = 'mb-0 mt-1';
        for (let i = 0; i < input.files.length; i++) {
            const li = document.createElement('li');
            li.innerHTML = '<small>' + input.files[i].name + ' (' + (input.files[i].size / 1024).toFixed(2) + ' KB)</small>';
            ul.appendChild(li);
        }
        fileList.appendChild(ul);
        listDiv.appendChild(fileList);
    }
    input.setAttribute('accept', '');
}

// Update selected count function
window.updateSelectedCount = function() {
    const selected = document.querySelectorAll('.student-checkbox:checked');
    const count = selected.length;
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('sendBulkBtn').disabled = count === 0;
}

// Send bulk email function
window.sendBulkEmail = function() {
    const checkboxes = document.querySelectorAll('.student-checkbox:checked');
    const selectedStudents = [];
    
    checkboxes.forEach(cb => {
        if (cb.closest('tr').style.display !== 'none') {
            selectedStudents.push({
                id: cb.dataset.studentId,
                name: cb.dataset.studentName,
                email: cb.dataset.studentEmail
            });
        }
    });
    
    if (selectedStudents.length === 0) {
        alert('Please select at least one student');
        return;
    }
    
    // Populate bulk email modal
    document.getElementById('bulkRecipientCount').textContent = selectedStudents.length;
    document.getElementById('bulkRecipientsList').innerHTML = selectedStudents.map(student => 
        `<small class="d-block">${student.name} (${student.email})</small>`
    ).join('');
    
    // Store selected students data
    window.selectedStudentsForBulkEmail = selectedStudents;
    
    new bootstrap.Modal(document.getElementById('bulkEmailModal')).show();
}

// Bulk email functionality
window.bulkEmailMode = false;

window.toggleBulkEmailMode = function() {
    window.bulkEmailMode = !window.bulkEmailMode;
    
    const bulkControls = document.getElementById('bulkEmailControls');
    const bulkHeaders = document.querySelectorAll('#bulkSelectHeader, .bulk-select-cell');
    
    if (window.bulkEmailMode) {
        if (bulkControls) bulkControls.style.display = 'block';
        bulkHeaders.forEach(el => el.style.display = 'table-cell');
        deselectAllStudents();
    } else {
        if (bulkControls) bulkControls.style.display = 'none';
        bulkHeaders.forEach(el => el.style.display = 'none');
        deselectAllStudents();
    }
}

window.selectAllStudents = function() {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(cb => {
        if (cb.closest('tr').style.display !== 'none') {
            cb.checked = true;
        }
    });
    document.getElementById('selectAllCheckbox').checked = true;
    updateSelectedCount();
}

window.deselectAllStudents = function() {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(cb => cb.checked = false);
    document.getElementById('selectAllCheckbox').checked = false;
    updateSelectedCount();
}

window.toggleAllStudents = function() {
    const selectAll = document.getElementById('selectAllCheckbox').checked;
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(cb => {
        if (cb.closest('tr').style.display !== 'none') {
            cb.checked = selectAll;
        }
    });
    updateSelectedCount();
}

// Clear old data function
function clearOldData() {
    if (confirm('Are you sure you want to remove ALL student data? This will delete all students from your hostel. This action cannot be undone.')) {
        console.log('Clearing old data...');
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="clear_old_data" value="1">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Remove duplicates function
function removeDuplicates() {
    if (confirm('Are you sure you want to remove duplicate students? This will keep only the first occurrence of each GRN. This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="remove_duplicates" value="1">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Auto-search functionality for students
function searchStudents() {
    const searchInput = document.getElementById('studentSearch');
    if (!searchInput) return;
    
    const searchTerm = searchInput.value.toLowerCase().trim();
    const table = document.getElementById('studentsTable');
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        let searchableText = '';
        
        cells.forEach(cell => {
            if (!cell.classList.contains('bulk-select-cell')) {
                searchableText += cell.textContent.toLowerCase() + ' ';
            }
        });
        
        if (!searchTerm || searchableText.includes(searchTerm)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    updateSearchCount(visibleCount, rows.length);
}

// Update search result count
function updateSearchCount(visible, total) {
    const countElement = document.getElementById('searchCount');
    if (!countElement) return;
    
    const searchInput = document.getElementById('studentSearch');
    if (searchInput && searchInput.value.trim()) {
        countElement.textContent = `Showing ${visible} of ${total} students`;
    } else {
        countElement.textContent = `Total: ${total} students`;
    }
}

// Clear search function
function clearSearch() {
    document.getElementById('studentSearch').value = '';
    searchStudents();
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize search functionality
    const searchInput = document.getElementById('studentSearch');
    if (searchInput) {
        searchInput.addEventListener('input', searchStudents);
    }
    
    // Handle bulk email form submission
    const bulkEmailForm = document.getElementById('bulkEmailForm');
    if (bulkEmailForm) {
        bulkEmailForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!window.selectedStudentsForBulkEmail || window.selectedStudentsForBulkEmail.length === 0) {
                alert('No students selected');
                return;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
            submitBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'send_bulk_email');
            formData.append('recipients', JSON.stringify(window.selectedStudentsForBulkEmail));
            formData.append('subject', document.getElementById('bulkEmailSubject').value);
            formData.append('message', document.getElementById('bulkEmailMessage').value);
            formData.append('email_type', document.getElementById('bulkEmailType').value);
            formData.append('attachment_links', document.getElementById('bulkEmailAttachmentLinks').value);
            
            // Add file attachments
            const files = document.getElementById('bulkEmailAttachments').files;
            for (let i = 0; i < files.length; i++) {
                formData.append('attachments[]', files[i]);
            }
            
            fetch('../handlers/send_bulk_email.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ Bulk email sent successfully!\nSent: ${data.sent_count}\nFailed: ${data.failed_count}`);
                    bootstrap.Modal.getInstance(document.getElementById('bulkEmailModal')).hide();
                    toggleBulkEmailMode();
                    this.reset();
                    document.getElementById('bulkRectorEmail').value = document.getElementById('rectorEmail').value;
                } else {
                    alert('❌ Error: ' + data.message);
                }
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            })
            .catch(error => {
                alert('❌ Error sending bulk email');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});



// Handle edit student form submission
document.addEventListener('DOMContentLoaded', function() {
    const editForm = document.getElementById('editStudentForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'update_student');
            formData.append('student_id', document.getElementById('editStudentId').value);
            formData.append('name', document.getElementById('editStudentName').value);
            formData.append('email', document.getElementById('editStudentEmail').value);
            formData.append('contact', document.getElementById('editStudentContact').value);
            formData.append('course', document.getElementById('editStudentCourse').value);
            formData.append('year', document.getElementById('editStudentYear').value);
            formData.append('room_no', document.getElementById('editStudentRoom').value);
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
            submitBtn.disabled = true;
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Student updated successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('editStudentModal')).hide();
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            })
            .catch(error => {
                alert('❌ Error updating student');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});
