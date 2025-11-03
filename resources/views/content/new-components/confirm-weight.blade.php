<!-- Confirm Weight Modal -->
<div class="modal fade" id="confirm-weight-modal" tabindex="-1" aria-labelledby="confirm-weight-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirm-weight-modal-label">
          Skenirana sirovina: <span id="scanned-material-name">-</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center justify-content-center gap-3 py-4">
          <div style="width: 30%;">
            <input 
              type="number" 
              class="form-control form-control-lg" 
              id="weight-input" 
              placeholder="0,00" 
              step="0.01"
              min="0"
            >
          </div>
          <div>
            <label for="weight-input" class="form-label mb-0 kg-label">kg</label>
          </div>
        </div>
      </div>
      <div class="modal-footer d-flex">
        <button type="button" class="btn btn-outline-primary flex-fill" data-bs-dismiss="modal">
          <i class="fa fa-times me-2"></i> Odustani
        </button>
        <button type="button" class="btn btn-primary flex-fill" id="confirm-add-sirovina-btn">
          <i class="fa fa-check me-2"></i> Dodaj sirovinu
        </button>
      </div>
    </div>
  </div>
</div>

<style>
  #confirm-weight-modal .modal-footer {
    padding: 1rem;
    border-top: 1px solid #dee2e6;
    gap: 1rem;
  }
  
  #confirm-weight-modal .modal-footer .btn {
    flex: 1;
  }
  
  #confirm-weight-modal .form-control-lg {
    font-size: 2.5rem;
    padding: 1.25rem 1.5rem;
    font-weight: 500;
  }
  
  #confirm-weight-modal .kg-label {
    font-size: 2.5rem;
    font-weight: 500;
    text-transform: lowercase;
  }
  
  #confirm-weight-modal .modal-body .row {
    padding: 1.5rem 0;
  }
</style>

<script>
  // Keyboard shortcut ALT+C to open confirm weight modal when sirovina scanner is open
  document.addEventListener('DOMContentLoaded', function() {
    const sirovinaModal = document.getElementById('sirovina-scanner-modal');
    const confirmWeightModal = document.getElementById('confirm-weight-modal');
    const scannedMaterialName = document.getElementById('scanned-material-name');
    
    if (sirovinaModal && confirmWeightModal) {
      let scannedMaterial = null;
      
      // Listen for keyboard shortcut ALT+C
      document.addEventListener('keydown', function(e) {
        // Check if ALT+C is pressed and sirovina scanner is open
        if (e.altKey && e.key === 'c' && sirovinaModal.classList.contains('show')) {
          e.preventDefault();
          
          // Get scanned material from materijaliData array (simulate scanning - get first item for demo)
          // In real implementation, this would come from QR scan result
          if (typeof materijaliData !== 'undefined' && materijaliData.length > 0) {
            // For demo, get first material. In production, this would be the scanned QR result
            scannedMaterial = materijaliData[0];
            scannedMaterialName.textContent = scannedMaterial.naziv || scannedMaterial.materijal || '-';
          } else {
            scannedMaterialName.textContent = 'Nepoznata sirovina';
          }
          
          // Close sirovina scanner and open confirm weight modal
          const sirovinaModalInstance = bootstrap.Modal.getInstance(sirovinaModal);
          if (sirovinaModalInstance) {
            sirovinaModalInstance.hide();
          }
          
          // Open confirm weight modal
          const confirmWeightModalInstance = new bootstrap.Modal(confirmWeightModal);
          confirmWeightModalInstance.show();
          
          // Focus on weight input
          setTimeout(function() {
            const weightInput = document.getElementById('weight-input');
            if (weightInput) {
              weightInput.focus();
            }
          }, 300);
        }
      });
      
      // Handle confirm button click
      const confirmBtn = document.getElementById('confirm-add-sirovina-btn');
      if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
          const weightInput = document.getElementById('weight-input');
          const weight = weightInput ? weightInput.value : '';
          
          if (!weight || parseFloat(weight) <= 0) {
            alert('Molimo unesite validnu težinu.');
            return;
          }
          
          // Here you would process the addition of the material
          console.log('Adding material:', scannedMaterial, 'Weight:', weight);
          
          // Close modal
          const confirmWeightModalInstance = bootstrap.Modal.getInstance(confirmWeightModal);
          if (confirmWeightModalInstance) {
            confirmWeightModalInstance.hide();
          }
          
          // Reset form
          if (weightInput) {
            weightInput.value = '';
          }
          scannedMaterial = null;
        });
      }
      
      // Reset material name when modal is closed
      confirmWeightModal.addEventListener('hidden.bs.modal', function() {
        scannedMaterialName.textContent = '-';
        const weightInput = document.getElementById('weight-input');
        if (weightInput) {
          weightInput.value = '';
        }
        scannedMaterial = null;
      });
    }
  });
</script>

