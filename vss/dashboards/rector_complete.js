// Complete the truncated JavaScript functions

function updateSelectedCount() {
    const selected = document.querySelectorAll('.student-checkbox:checked');
    const count = selected.length;
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
    
    document.getElementById('bulkRecipientCount').textContent = selectedStudents.length;
    document.getElementById('bulkRecipientsList').innerHTML = selectedStudents.map(student => 
        `<small class="d-block">${student.name} (${student.email})</small>`
    ).join('');
    
    window.selectedStudentsForBulkEmail = selectedStudents;
    new bootstrap.Modal(document.getElementById('bulkEmailModal')).show();
}

// Initialize bulk email form handler
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
            formData.append('recipients', JSON.stringify(window.selectedStudentsForBulkEmail));
            formData.append('subject', document.getElementById('bulkEmailSubject').value);
            formData.append('message', document.getElementById('bulkEmailMessage').value);
            formData.append('email_type', document.getElementById('bulkEmailType').value);
            formData.append('include_admission_form', document.getElementById('bulkIncludeAdmissionForm').checked ? '1' : '0');
            formData.append('attachment_links', document.getElementById('bulkEmailAttachmentLinks').value);
            
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