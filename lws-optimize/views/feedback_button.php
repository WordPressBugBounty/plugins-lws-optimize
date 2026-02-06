<?php
$t = [
    'buttonText' => '💬 Feedback',
    'modalTitle' => 'Partagez votre avis',
    'typeLabel' => 'Type de retour :',
    'nameLabel' => 'Nom (optionnel) :',
    'emailLabel' => 'Email (optionnel) :',
    'feedbackLabel' => 'Votre message :',
    'namePlaceholder' => 'Votre nom',
    'emailPlaceholder' => 'votre@email.com',
    'feedbackPlaceholder' => 'Décrivez votre idée, suggestion ou problème...',
    'cancelButton' => 'Annuler',
    'submitButton' => 'Envoyer',
    'submittingButton' => 'Envoi...',
    'successMessage' => 'Votre retour a bien été pris en compte. Merci de participer à l\'amélioration de notre service.',
    'errorMessage' => 'Erreur lors de l\'envoi. Veuillez réessayer.',
    'errorFormMessage' => 'Le formulaire est incomplet ou invalide. Veuillez vérifier vos informations.',
    'defaultErrorMessage' => 'Une erreur inattendue est survenue. Veuillez réessayer plus tard.',
    'types' => [
        'suggestion' => 'Suggestion',
        'bug' => 'Bug / Problème',
        'improvement' => 'Amélioration',
        'other' => 'Autre'
    ]
];
?>

<!-- Floating Feedback Button -->
<button class="feedbackButton" onclick="openFeedbackModal()" aria-label="<?php echo htmlspecialchars($t['buttonText']) ?>">
    <?php echo htmlspecialchars($t['buttonText']) ?>
</button>

<!-- Modal -->
<div class="modalOverlay" id="feedbackModal" style="display: none;">
    <div class="modalContent">
        <div class="modalHeader">
            <h3><?php echo htmlspecialchars($t['modalTitle']) ?></h3>
            <button class="closeButton" onclick="closeFeedbackModal()" aria-label="Close">×</button>
        </div>

        <div id="feedbackForm" class="form">
            <form onsubmit="handleSubmit(event)">
                <div class="formGroup">
                    <label for="feedbackType"><?php echo htmlspecialchars($t['typeLabel']) ?></label>
                    <select id="feedbackType" name="type" required>
                        <?php foreach ($t['types'] as $key => $value): ?>
                            <option value="<?php echo htmlspecialchars($key) ?>"><?php echo htmlspecialchars($value) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="formGroup">
                    <label for="feedbackName"><?php echo htmlspecialchars($t['nameLabel']) ?></label>
                    <input type="text" id="feedbackName" name="name" placeholder="<?php echo htmlspecialchars($t['namePlaceholder']) ?>">
                </div>

                <div class="formGroup">
                    <label for="feedbackEmail"><?php echo htmlspecialchars($t['emailLabel']) ?></label>
                    <input type="email" id="feedbackEmail" name="email" placeholder="<?php echo htmlspecialchars($t['emailPlaceholder']) ?>">
                </div>

                <div class="formGroup">
                    <label for="feedbackMessage"><?php echo htmlspecialchars($t['feedbackLabel']) ?></label>
                    <textarea id="feedbackMessage" name="feedback" placeholder="<?php echo htmlspecialchars($t['feedbackPlaceholder']) ?>" rows="4" required></textarea>
                </div>

                <div class="formActions">
                    <button type="button" onclick="closeFeedbackModal()" class="cancelButton">
                        <?php echo htmlspecialchars($t['cancelButton']) ?>
                    </button>
                    <button type="submit" class="submitButton" id="submitBtn">
                        <?php echo htmlspecialchars($t['submitButton']) ?>
                    </button>
                </div>
            </form>
        </div>

    <div id="feedbackMessage"></div>
    </div>
</div>

<script>
const translations = <?php echo json_encode($t) ?>;
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
            success: function(data) {
                submitBtn.innerHTML = translations.submitButton;
                submitBtn.disabled = false;

                if (data === null || typeof data != 'string'){
                    return 0;
                }

                try{
                    var returnData = JSON.parse(data);
                } catch (e){
                    return 0;
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
