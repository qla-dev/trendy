<!-- QR Scanner Modal for Sirovina -->
<div class="modal fade" id="sirovina-scanner-modal" tabindex="-1" aria-labelledby="sirovina-scanner-modal-label" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background: transparent; border: none;">
      <div class="modal-body p-0 text-center">
        <h4 class="text-white mb-4" id="sirovina-scanner-modal-label">
          Dodavanje sirovine za RN <span id="sirovina-rn-number"></span>
        </h4>
        <div class="qr-scanner-container position-relative" style="max-width: 400px; margin: 0 auto;">
          <!-- QR Scanner Frame -->
          <div class="qr-scanner-frame position-relative" style="width: 100%; padding-top: 100%; background: rgba(255, 255, 255, 0.1); border: 2px solid var(--bs-success, #28c76f); border-radius: 12px; overflow: hidden;">
            <!-- Corner indicators -->
            <div class="qr-corner qr-corner-top-left" style="position: absolute; top: 0; left: 0; width: 40px; height: 40px; border-top: 3px solid var(--bs-success, #28c76f); border-left: 3px solid var(--bs-success, #28c76f);"></div>
            <div class="qr-corner qr-corner-top-right" style="position: absolute; top: 0; right: 0; width: 40px; height: 40px; border-top: 3px solid var(--bs-success, #28c76f); border-right: 3px solid var(--bs-success, #28c76f);"></div>
            <div class="qr-corner qr-corner-bottom-left" style="position: absolute; bottom: 0; left: 0; width: 40px; height: 40px; border-bottom: 3px solid var(--bs-success, #28c76f); border-left: 3px solid var(--bs-success, #28c76f);"></div>
            <div class="qr-corner qr-corner-bottom-right" style="position: absolute; bottom: 0; right: 0; width: 40px; height: 40px; border-bottom: 3px solid var(--bs-success, #28c76f); border-right: 3px solid var(--bs-success, #28c76f);"></div>
            
            <!-- Scanning line animation -->
            <div class="qr-scan-line" style="position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--bs-success, #28c76f), transparent); animation: scanLineSirovina 2s linear infinite;"></div>
            
            <!-- Grid pattern overlay -->
            <div class="qr-grid" style="position: absolute; inset: 0; background-image: 
              linear-gradient(rgba(40, 199, 111, 0.1) 1px, transparent 1px),
              linear-gradient(90deg, rgba(40, 199, 111, 0.1) 1px, transparent 1px);
              background-size: 20px 20px; opacity: 0.3;"></div>
          </div>
        </div>
        <button type="button" class="btn btn-secondary mt-4" data-bs-dismiss="modal" aria-label="Zatvori">
          <i class="fa fa-times me-50"></i> Zatvori
        </button>
      </div>
    </div>
  </div>
</div>

<style>
  /* Very dark backdrop for QR scanner modal */
  .sirovina-scanner-backdrop {
    background-color: rgba(0, 0, 0, 0.95) !important;
    opacity: 1 !important;
  }
  
  /* Scanning line animation */
  @keyframes scanLineSirovina {
    0% {
      top: 0;
      opacity: 1;
    }
    50% {
      opacity: 0.8;
    }
    100% {
      top: 100%;
      opacity: 0;
    }
  }
  
  /* Corner pulse animation */
  @keyframes cornerPulseSirovina {
    0%, 100% {
      opacity: 1;
      transform: scale(1);
    }
    50% {
      opacity: 0.7;
      transform: scale(1.1);
    }
  }
  
  #sirovina-scanner-modal .qr-corner {
    animation: cornerPulseSirovina 2s ease-in-out infinite;
  }
  
  #sirovina-scanner-modal .qr-corner-top-right {
    animation-delay: 0.5s;
  }
  
  #sirovina-scanner-modal .qr-corner-bottom-left {
    animation-delay: 1s;
  }
  
  #sirovina-scanner-modal .qr-corner-bottom-right {
    animation-delay: 1.5s;
  }
  
  /* Grid animation */
  @keyframes gridMoveSirovina {
    0% {
      background-position: 0 0;
    }
    100% {
      background-position: 20px 20px;
    }
  }
  
  #sirovina-scanner-modal .qr-grid {
    animation: gridMoveSirovina 3s linear infinite;
  }
  
  /* Ensure modal content is transparent */
  #sirovina-scanner-modal .modal-dialog {
    background: transparent;
  }
  
  #sirovina-scanner-modal .modal-content {
    background: transparent;
    box-shadow: none;
    border: none;
  }
</style>

<script>
  // Ensure very dark backdrop when QR scanner modal opens and get RN number
  document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('sirovina-scanner-modal');
    if (modal) {
      // Get RN number from invoice number element
      modal.addEventListener('show.bs.modal', function() {
        const invoiceNumberElement = document.querySelector('.invoice-number');
        if (invoiceNumberElement) {
          const rnNumber = invoiceNumberElement.textContent.trim();
          const rnNumberSpan = document.getElementById('sirovina-rn-number');
          if (rnNumberSpan) {
            rnNumberSpan.textContent = rnNumber;
          }
        }
        
        // Set a timeout to catch the backdrop after Bootstrap creates it
        setTimeout(function() {
          const backdrop = document.querySelector('.modal-backdrop');
          if (backdrop) {
            backdrop.classList.add('sirovina-scanner-backdrop');
            backdrop.style.backgroundColor = 'rgba(0, 0, 0, 0.95)';
            backdrop.style.opacity = '1';
          }
        }, 50);
      });
      
      // Use MutationObserver to watch for backdrop creation
      const observer = new MutationObserver(function(mutations) {
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop && modal.classList.contains('show')) {
          backdrop.classList.add('sirovina-scanner-backdrop');
          backdrop.style.backgroundColor = 'rgba(0, 0, 0, 0.95)';
          backdrop.style.opacity = '1';
        }
      });
      
      // Observe body for backdrop changes
      observer.observe(document.body, {
        childList: true,
        subtree: true
      });
      
      // Also listen to modal events
      modal.addEventListener('shown.bs.modal', function() {
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
          backdrop.classList.add('sirovina-scanner-backdrop');
          backdrop.style.backgroundColor = 'rgba(0, 0, 0, 0.95)';
          backdrop.style.opacity = '1';
        }
      });
    }
  });
</script>

