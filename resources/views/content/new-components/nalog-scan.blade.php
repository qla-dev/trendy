<!-- QR Scanner Modal -->
<div class="modal fade" id="qr-scanner-modal" tabindex="-1" aria-labelledby="qr-scanner-modal-label" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background: transparent; border: none;">
      <div class="modal-body p-0 text-center">
        <h4 class="text-white mb-4" id="qr-scanner-modal-label">Skeniraj QR code radnog naloga</h4>
        <div class="qr-scanner-container position-relative" style="max-width: 400px; margin: 0 auto;">
          <!-- QR Scanner Frame -->
          <div class="qr-scanner-frame position-relative" style="width: 100%; padding-top: 100%; background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(255, 255, 255, 0.3); border-radius: 12px; overflow: hidden;">
            <!-- Corner indicators -->
            <div class="qr-corner qr-corner-top-left" style="position: absolute; top: 0; left: 0; width: 40px; height: 40px; border-top: 3px solid var(--bs-primary, #495B73); border-left: 3px solid var(--bs-primary, #495B73);"></div>
            <div class="qr-corner qr-corner-top-right" style="position: absolute; top: 0; right: 0; width: 40px; height: 40px; border-top: 3px solid var(--bs-primary, #495B73); border-right: 3px solid var(--bs-primary, #495B73);"></div>
            <div class="qr-corner qr-corner-bottom-left" style="position: absolute; bottom: 0; left: 0; width: 40px; height: 40px; border-bottom: 3px solid var(--bs-primary, #495B73); border-left: 3px solid var(--bs-primary, #495B73);"></div>
            <div class="qr-corner qr-corner-bottom-right" style="position: absolute; bottom: 0; right: 0; width: 40px; height: 40px; border-bottom: 3px solid var(--bs-primary, #495B73); border-right: 3px solid var(--bs-primary, #495B73);"></div>
            
            <!-- Scanning line animation -->
            <div class="qr-scan-line" style="position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--bs-primary, #495B73), transparent); animation: scanLine 2s linear infinite;"></div>
            
            <!-- Grid pattern overlay -->
            <div class="qr-grid" style="position: absolute; inset: 0; background-image: 
              linear-gradient(rgba(115, 103, 240, 0.1) 1px, transparent 1px),
              linear-gradient(90deg, rgba(115, 103, 240, 0.1) 1px, transparent 1px);
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
  .qr-scanner-backdrop {
    background-color: rgba(0, 0, 0, 0.95) !important;
    opacity: 1 !important;
  }
  
  /* Scanning line animation */
  @keyframes scanLine {
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
  @keyframes cornerPulse {
    0%, 100% {
      opacity: 1;
      transform: scale(1);
    }
    50% {
      opacity: 0.7;
      transform: scale(1.1);
    }
  }
  
  .qr-corner {
    animation: cornerPulse 2s ease-in-out infinite;
  }
  
  .qr-corner-top-right {
    animation-delay: 0.5s;
  }
  
  .qr-corner-bottom-left {
    animation-delay: 1s;
  }
  
  .qr-corner-bottom-right {
    animation-delay: 1.5s;
  }
  
  /* Grid animation */
  @keyframes gridMove {
    0% {
      background-position: 0 0;
    }
    100% {
      background-position: 20px 20px;
    }
  }
  
  .qr-grid {
    animation: gridMove 3s linear infinite;
  }
  
  /* Ensure modal content is transparent */
  #qr-scanner-modal .modal-dialog {
    background: transparent;
  }
  
  #qr-scanner-modal .modal-content {
    background: transparent;
    box-shadow: none;
    border: none;
  }
</style>

<script>
  // Ensure very dark backdrop when QR scanner modal opens
  document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('qr-scanner-modal');
    if (modal) {
      // Use MutationObserver to watch for backdrop creation
      const observer = new MutationObserver(function(mutations) {
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop && modal.classList.contains('show')) {
          backdrop.classList.add('qr-scanner-backdrop');
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
          backdrop.classList.add('qr-scanner-backdrop');
          backdrop.style.backgroundColor = 'rgba(0, 0, 0, 0.95)';
          backdrop.style.opacity = '1';
        }
      });
      
      modal.addEventListener('show.bs.modal', function() {
        // Set a timeout to catch the backdrop after Bootstrap creates it
        setTimeout(function() {
          const backdrop = document.querySelector('.modal-backdrop');
          if (backdrop) {
            backdrop.classList.add('qr-scanner-backdrop');
            backdrop.style.backgroundColor = 'rgba(0, 0, 0, 0.95)';
            backdrop.style.opacity = '1';
          }
        }, 50);
      });
    }
  });
</script>

