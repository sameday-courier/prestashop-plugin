document.addEventListener('DOMContentLoaded', function() {
    let weightErrorLoaded = false;

    function checkWeightAndCarrier(carrierId) {
        const totalWeight = window.cartTotalWeight || 0;
        const samedayCarrierIds = window.samedayCarrierIds || [];
        const script = document.createElement('script');

        // Check if weight > 1500 AND carrier is a Sameday carrier
        if (totalWeight > 1500 && samedayCarrierIds.includes(carrierId)) {
            script.src = window.weightErrorJsPath;
            script.onload = function() {
                weightErrorLoaded = true;
                // Trigger weight error function if it exists
                if (typeof showWeightError === 'function') {
                    showWeightError();
                }
            };
            document.head.appendChild(script);
        } else if (weightErrorLoaded && typeof hideWeightError === 'function') {
            hideWeightError();
        }
    }

    // Check on page load with current carrier
    const currentCarrier = document.querySelector('input[name^="delivery_option"]:checked');
    if (currentCarrier) {
        checkWeightAndCarrier(currentCarrier.value.replace(',',''));
    }

    // Listen for carrier changes
    document.querySelectorAll('input[name^="delivery_option"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.checked) {
                checkWeightAndCarrier(this.value.replace(',',''));
            }
        });
    });

});
