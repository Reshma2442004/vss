<!-- Send Message Modal -->
<div class="modal fade" id="sendMessageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="messageForm">
                    <input type="hidden" id="recipientType">
                    <input type="hidden" id="recipientId">
                    <div class="mb-3">
                        <label class="form-label">To:</label>
                        <input type="text" class="form-control" id="recipientMail" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject:</label>
                        <input type="text" class="form-control" id="messageSubject" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message:</label>
                        <textarea class="form-control" id="messageContent" rows="5" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="sendMessage()">Send Message</button>
            </div>
        </div>
    </div>
</div>

<script>
// Open message modal
function openMessageModal(type, id, mail) {
    document.getElementById('recipientType').value = type;
    document.getElementById('recipientId').value = id;
    document.getElementById('recipientMail').value = mail;
    document.getElementById('messageSubject').value = '';
    document.getElementById('messageContent').value = '';
    new bootstrap.Modal(document.getElementById('sendMessageModal')).show();
}

// Send message
function sendMessage() {
    const formData = new FormData();
    formData.append('action', 'send_email');
    formData.append('recipient_type', document.getElementById('recipientType').value);
    formData.append('recipient_id', document.getElementById('recipientId').value);
    formData.append('subject', document.getElementById('messageSubject').value);
    formData.append('message', document.getElementById('messageContent').value);
    
    fetch('../handlers/send_email.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Message sent successfully!');
            bootstrap.Modal.getInstance(document.getElementById('sendMessageModal')).hide();
        } else {
            alert('❌ Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('❌ Error: ' + error.message);
    });
}
</script>