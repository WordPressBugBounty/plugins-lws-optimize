<?php
if (!defined('ABSPATH')) exit;
$t = [
    'buttonText' => __('Feedback', 'lws-optimize'),
    'modalTitle' => __('Share your feedback', 'lws-optimize'),
    'typeLabel' => __('Feedback type:', 'lws-optimize'),
    'nameLabel' => __('Name (optional):', 'lws-optimize'),
    'emailLabel' => __('Email (optional):', 'lws-optimize'),
    'feedbackLabel' => __('Your message:', 'lws-optimize'),
    'namePlaceholder' => __('Your name', 'lws-optimize'),
    'emailPlaceholder' => 'your@email.com',
    'feedbackPlaceholder' => __('Describe your idea, suggestion or issue...', 'lws-optimize'),
    'cancelButton' => __('Cancel', 'lws-optimize'),
    'submitButton' => __('Send', 'lws-optimize'),
    'submittingButton' => __('Sending...', 'lws-optimize'),
    'successMessage' => __('Your feedback has been received. Thank you for helping us improve our service.', 'lws-optimize'),
    'errorMessage' => __('Error while sending. Please try again.', 'lws-optimize'),
    'errorFormMessage' => __('The form is incomplete or invalid. Please check your information.', 'lws-optimize'),
    'defaultErrorMessage' => __('An unexpected error occurred. Please try again later.', 'lws-optimize'),
    'types' => [
        'suggestion' => __('Suggestion', 'lws-optimize'),
        'bug' => __('Bug / Issue', 'lws-optimize'),
        'improvement' => __('Improvement', 'lws-optimize'),
        'other' => __('Other', 'lws-optimize')
    ]
];
?>

<!-- Floating Feedback Button -->
<button class="feedbackButton" onclick="openFeedbackModal()" aria-label="<?php echo esc_attr($t['buttonText']) ?>">
    <span class="feedbackButton__text"><?php echo esc_html($t['buttonText']) ?></span>
</button>

<!-- Modal -->
<div class="modalOverlay" id="feedbackModal" style="display: none;">
    <div class="modalContent">
        <div class="modalHeader">
            <h3><?php echo esc_html($t['modalTitle']) ?></h3>
            <button class="closeButton" onclick="closeFeedbackModal()" aria-label="<?php echo esc_attr__('Close', 'lws-optimize'); ?>">×</button>
        </div>

        <div id="feedbackForm" class="form">
            <form onsubmit="handleSubmit(event)">
                <div class="formGroup">
                    <label for="feedbackType"><?php echo esc_html($t['typeLabel']) ?></label>
                    <select id="feedbackType" name="type" required>
                        <?php foreach ($t['types'] as $key => $value): ?>
                            <option value="<?php echo esc_attr($key) ?>"><?php echo esc_html($value) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="formGroup">
                    <label for="feedbackName"><?php echo esc_html($t['nameLabel']) ?></label>
                    <input type="text" id="feedbackName" name="name" placeholder="<?php echo esc_attr($t['namePlaceholder']) ?>">
                </div>

                <div class="formGroup">
                    <label for="feedbackEmail"><?php echo esc_html($t['emailLabel']) ?></label>
                    <input type="email" id="feedbackEmail" name="email" placeholder="<?php echo esc_attr($t['emailPlaceholder']) ?>">
                </div>

                <div class="formGroup">
                    <label for="feedbackMessage"><?php echo esc_html($t['feedbackLabel']) ?></label>
                    <textarea id="feedbackMessage" name="feedback" placeholder="<?php echo esc_attr($t['feedbackPlaceholder']) ?>" rows="4" required></textarea>
                </div>

                <div class="formActions">
                    <button type="button" onclick="closeFeedbackModal()" class="cancelButton">
                        <?php echo esc_html($t['cancelButton']) ?>
                    </button>
                    <button type="submit" class="submitButton" id="submitBtn">
                        <?php echo esc_html($t['submitButton']) ?>
                    </button>
                </div>
            </form>
        </div>

    <div id="feedbackMessage"></div>
    </div>
</div>

<script>
const translations = <?php echo wp_json_encode($t) ?>;
let isSubmitting = false;

function openFeedbackModal() {
    document.getElementById('feedbackModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeFeedbackModal() {
    document.getElementById('feedbackModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    resetForm();
}

function resetForm() {
    document.getElementById('feedbackType').value = 'suggestion';
    document.getElementById('feedbackName').value = '';
    document.getElementById('feedbackEmail').value = '';
    document.getElementById('feedbackMessage').value = '';
}

function handleSubmit(event) {
    event.preventDefault();

    let submitBtn = document.getElementById('submitBtn');

    submitBtn.innerHTML =
    `<div class="load-animated">
        <div class="line"></div>
        <div class="line"></div>
        <div class="line"></div>
    </div>`;
    submitBtn.disabled = true;

    try {
        const formData = {
            type: document.getElementById('feedbackType').value,
            name: document.getElementById('feedbackName').value,
            email: document.getElementById('feedbackEmail').value,
            feedback: document.getElementById('feedbackMessage').value,
            timestamp: new Date().toISOString(),
            page: window.location.href.split('&key=')[0].split('&hash=')[0],
        };

        let ajaxRequest = jQuery.ajax({
            url: ajaxurl,
            type: "POST",
            timeout: 120000,
            context: document.body,
            data: {
                form: formData,
                action: "lwsOp_sendFeedbackUser",
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsOP_sendFeedbackUser')); ?>'
            },
            success: function(returnData) {
                submitBtn.innerHTML = translations.submitButton;
                submitBtn.disabled = false;

                if (!isValidResponse(returnData)) {
                    console.error('Invalid AJAX response', returnData);
                    return;
                }

                switch (returnData['code']){
                    case 'SUCCESS':
                        closeFeedbackModal();
                        callPopup('success', translations.successMessage);
                        break;
                    case 'ERROR_FORM':
                        callPopup('error', translations.errorFormMessage);
                        break;
                    case 'ERROR':
                        callPopup('error', translations.errorMessage);
                        break;
                    default:
                        callPopup('error', translations.defaultErrorMessage);
                        break;
                }
            },
            error: function(error) {
                callPopup('error', translations.defaultErrorMessage);
                submitBtn.innerHTML = translations.submitButton;
                submitBtn.disabled = false;
            }
        });
    } catch (error) {
        console.error('Feedback submission error:', error);
        submitBtn.innerHTML = translations.submitButton;
        submitBtn.disabled = false;
    }
}

// Close modal when clicking outside
document.getElementById('feedbackModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeFeedbackModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('feedbackModal').style.display === 'flex') {
        closeFeedbackModal();
    }
});
</script>
