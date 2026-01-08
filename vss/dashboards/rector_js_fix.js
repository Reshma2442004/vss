// Complete JavaScript functions for bulk email and search functionality

function selectAllStudents() {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(cb => {
        if (cb.closest('tr').style.display !== 'none') {
            cb.checked = true;
        }
    });
    document.getElementById('selectAllCheckbox').checked = true;
    updateSelectedCount();
}

function deselectAllStudents() {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = false;
    });
    document.getElementById('selectAllCheckbox').checked = false;
    updateSelectedCount();
}

function toggleAllStudents() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.student-checkbox');
    
    checkboxes.forEach(cb => {
        if (cb.closest('tr').style.display !== 'none') {
            cb.checked = selectAllCheckbox.checked;
        }
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.student-checkbox:checked');
    const visibleChecked = Array.from(checkboxes).filter(cb => 
        cb.closest('tr').style.display !== 'none'
    );
    
    const count = visibleChecked.length;
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('sendBulkBtn').disabled = count === 0;
}

function sendBulkEmail() {
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
    
    const recipientsList = document.getElementById('bulkRecipientsList');
    recipientsList.innerHTML = selectedStudents.map(student => 
        `<small class="d-block">${student.name} (${student.email})</small>`
    ).join('');
    
    // Store selected students data
    window.selectedStudentsForBulkEmail = selectedStudents;
    
    new bootstrap.Modal(document.getElementById('bulkEmailModal')).show();
}

// Handle bulk email form submission
document.addEventListener('DOMContentLoaded', function() {
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
            formData.append('students', JSON.stringify(window.selectedStudentsForBulkEmail));
            formData.append('subject', document.getElementById('bulkEmailSubject').value);
            formData.append('message', document.getElementById('bulkEmailMessage').value);
            formData.append('email_type', document.getElementById('bulkEmailType').value);
            formData.append('include_admission_form', document.getElementById('bulkIncludeAdmissionForm').checked ? '1' : '0');
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
                    toggleBulkEmailMode(); // Exit bulk mode
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