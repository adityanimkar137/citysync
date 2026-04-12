/**
 * CitySync - Shared JavaScript Utilities
 * Loaded on every page via <script src="js/app.js">
 */

/**
 * Show a toast notification
 * @param {string} message
 * @param {string} type - 'success' | 'error' | 'warning' | ''
 * @param {number} duration - ms before auto-dismiss (default 4000)
 */
function showToast(message, type = '', duration = 4000) {
    const container = $('#toast-container');
    if (!container.length) return;

    const icons = {
        success: '✅',
        error:   '❌',
        warning: '⚠️',
        '':      'ℹ️',
    };

    const toast = $(`
        <div class="toast ${type}">
            <span>${icons[type] || ''}  </span>
            <span>${message}</span>
        </div>
    `);

    container.append(toast);

    // Auto dismiss
    setTimeout(() => {
        toast.css({ opacity: 0, transition: 'opacity .3s' });
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

/**
 * Format a date string nicely
 * @param {string} dateStr
 * @returns {string}
 */
function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-IN', {
        day:   '2-digit',
        month: 'short',
        year:  'numeric',
    });
}

/**
 * Escape HTML for safe insertion
 * @param {string} str
 * @returns {string}
 */
function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

// Voice Input Functionality
document.addEventListener('DOMContentLoaded', function() {
    const voiceBtn = document.getElementById('voice-btn');
    const descriptionTextarea = document.getElementById('description');
    const voiceStatus = document.getElementById('voice-status');

    let recognition = null;
    let isListening = false;

    // Check for SpeechRecognition support
    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        recognition = new SpeechRecognition();
        recognition.continuous = false;
        recognition.interimResults = false;
        recognition.lang = 'en-IN'; // Supports English, Hindi, etc. for Indian context

        recognition.onstart = function() {
            isListening = true;
            voiceBtn.textContent = '🎙️';
            voiceBtn.title = 'Listening... Click to stop';
            voiceStatus.textContent = 'Listening...';
            voiceStatus.style.color = 'var(--teal)';
        };

        recognition.onresult = function(event) {
            const transcript = event.results[0][0].transcript;
            const currentText = descriptionTextarea.value;
            descriptionTextarea.value = currentText ? currentText + ' ' + transcript : transcript;
            descriptionTextarea.focus();
        };

        recognition.onend = function() {
            isListening = false;
            voiceBtn.textContent = '🎤';
            voiceBtn.title = 'Click to record voice input';
            voiceStatus.textContent = '';
        };

        recognition.onerror = function(event) {
            console.error('Speech recognition error:', event.error);
            showToast('Voice input error: ' + event.error, 'error');
            voiceStatus.textContent = 'Error';
            voiceStatus.style.color = 'var(--red)';
            setTimeout(() => voiceStatus.textContent = '', 2000);
        };
    } else {
        // Fallback if not supported
        if (voiceBtn) {
            voiceBtn.disabled = true;
            voiceBtn.title = 'Voice input not supported in this browser';
            voiceStatus.textContent = 'Voice input not supported';
            voiceStatus.style.color = 'var(--gray-500)';
        }
    }

    if (voiceBtn && recognition) {
        voiceBtn.addEventListener('click', function() {
            if (isListening) {
                recognition.stop();
            } else {
                recognition.start();
            }
        });
    }

    // GPS Location Functionality
    const gpsBtn = document.getElementById('gps-btn');
    const locationDisplay = document.getElementById('location-display');
    const locationHidden = document.getElementById('location');
    const latitudeHidden = document.getElementById('latitude');
    const longitudeHidden = document.getElementById('longitude');

    if (gpsBtn) {
        gpsBtn.addEventListener('click', function() {
            if (!navigator.geolocation) {
                showToast('Geolocation is not supported by this browser.', 'error');
                return;
            }

            gpsBtn.disabled = true;
            gpsBtn.textContent = 'Getting Location...';

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;

                    // Reverse geocode to get address
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                        .then(response => response.json())
                        .then(data => {
                            const address = data.display_name || 'Address not found';
                            locationDisplay.value = `Address: ${address}, Coordinates: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                            locationHidden.value = address;
                            latitudeHidden.value = lat;
                            longitudeHidden.value = lng;
                            showToast('Location fetched successfully!', 'success');
                        })
                        .catch(error => {
                            console.error('Reverse geocoding error:', error);
                            locationDisplay.value = `Coordinates: ${lat.toFixed(6)}, ${lng.toFixed(6)} (Address fetch failed)`;
                            locationHidden.value = '';
                            latitudeHidden.value = lat;
                            longitudeHidden.value = lng;
                            showToast('Location coordinates fetched, but address lookup failed.', 'warning');
                        })
                        .finally(() => {
                            gpsBtn.disabled = false;
                            gpsBtn.textContent = '📍 Get GPS Location';
                        });
                },
                function(error) {
                    console.error('Geolocation error:', error);
                    let errorMsg = 'Unable to retrieve your location.';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMsg = 'Location access denied by user.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMsg = 'Location information is unavailable.';
                            break;
                        case error.TIMEOUT:
                            errorMsg = 'Location request timed out.';
                            break;
                    }
                    showToast(errorMsg, 'error');
                    gpsBtn.disabled = false;
                    gpsBtn.textContent = '📍 Get GPS Location';
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 300000 // 5 minutes
                }
            );
        });
    }
});


