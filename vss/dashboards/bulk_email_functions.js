// Bulk email functionality for rector dashboard

let bulkEmailMode = false;

function selectAllStudents() {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(cb => {
        if (cb.closest('tr').style.display !== 'none') {
            cb.checked = true;
        }
    });
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = true;
    }
    updateSelectedCount();
}

function deselectAllStudents() {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = false;
    });
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
    }
    updateSelectedCount();
}

function toggleAllStudents() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (!selectAllCheckbox) return;
    
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
    const selectedCountEl = document.getElementById('selectedCount');
    const sendBulkBtn = document.getElementById('sendBulkBtn');
    
    if (selectedCountEl) {
        selectedCountEl.textContent = count;
    }
    if (sendBulkBtn) {
        sendBulkBtn.disabled = count === 0;
    }
}

function toggleBulkEmailMode() {
    bulkEmailMode = !bulkEmailMode;
    const bulkControls = document.getElementById('bulkEmailControls');
    const bulkHeaders = document.querySelectorAll('#bulkSelectHeader, .bulk-select-cell');
    
    if (bulkEmailMode) {
        if (bulkControls) bulkControls.style.display = 'block';
        bulkHeaders.forEach(el => el.style.display = 'table-cell');
        deselectAllStudents();
    } else {
        if (bulkControls) bulkControls.style.display = 'none';
        bulkHeaders.forEach(el => el.style.display = 'none');
        deselectAllStudents();
    }
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
    const bulkRecipientCount = document.getElementById('bulkRecipientCount');
    if (bulkRecipientCount) {
        bulkRecipientCount.textContent = selectedStudents.length;
    }
    
    const recipientsList = document.getElementById('bulkRecipientsList');
    if (recipientsList) {
        recipientsList.innerHTML = selectedStudents.map(student => 
            `<small class="d-block">${student.name} (${student.email})</small>`
        ).join('');
    }
    
    // Store selected students data
    window.selectedStudentsForBulkEmail = selectedStudents;
    
    const bulkEmailModal = document.getElementById('bulkEmailModal');
    if (bulkEmailModal) {
        new bootstrap.Modal(bulkEmailModal).show();
    }
}

// Initialize bulk email form handler when DOM is loaded
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
            
            const bulkEmailSubject = document.getElementById('bulkEmailSubject');
            const bulkEmailMessage = document.getElementById('bulkEmailMessage');
            const bulkEmailType = document.getElementById('bulkEmailType');
            const bulkIncludeAdmissionForm = document.getElementById('bulkIncludeAdmissionForm');
            const bulkEmailAttachmentLinks = document.getElementById('bulkEmailAttachmentLinks');
            const bulkEmailAttachments = document.getElementById('bulkEmailAttachments');
            
            if (bulkEmailSubject) formData.append('subject', bulkEmailSubject.value);
            if (bulkEmailMessage) formData.append('message', bulkEmailMessage.value);
            if (bulkEmailType) formData.append('email_type', bulkEmailType.value);
            if (bulkIncludeAdmissionForm) formData.append('include_admission_form', bulkIncludeAdmissionForm.checked ? '1' : '0');
            if (bulkEmailAttachmentLinks) formData.append('attachment_links', bulkEmailAttachmentLinks.value);
            
            // Add file attachments
            if (bulkEmailAttachments && bulkEmailAttachments.files) {
                for (let i = 0; i < bulkEmailAttachments.files.length; i++) {
                    formData.append('attachments[]', bulkEmailAttachments.files[i]);
                }
            }
            
            fetch('../handlers/send_bulk_email.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ Bulk email sent successfully!\nSent: ${data.sent_count || 0}\nFailed: ${data.failed_count || 0}`);
                    const bulkEmailModal = document.getElementById('bulkEmailModal');
                    if (bulkEmailModal) {
                        bootstrap.Modal.getInstance(bulkEmailModal).hide();
                    }
                    toggleBulkEmailMode(); // Exit bulk mode
                    this.reset();
                    const bulkRectorEmail = document.getElementById('bulkRectorEmail');
                    const rectorEmail = document.getElementById('rectorEmail');
                    if (bulkRectorEmail && rectorEmail) {
                        bulkRectorEmail.value = rectorEmail.value;
                    }
                } else {
                    alert('❌ Error: ' + (data.message || 'Unknown error'));
                }
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            })
            .catch(error => {
                console.error('Bulk email error:', error);
                alert('❌ Error sending bulk email');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});