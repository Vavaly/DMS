// Constants
const SELECTORS = {
    uploadModal: '#uploadModal',
    uploadType: '#uploadType',
    fileField: '#fileUploadField',
    linkField: '#linkUploadField',
    fileInput: '#documentFile',
    linkInput: '#documentLink',
    profileToggle: '#profileToggle',
    dropdownMenu: '#dropdownMenu'
};

class DocumentManager {
    constructor() {
        this.initializeEventListeners();
    }

    // Initialize all event listeners
    initializeEventListeners() {
        // Upload type toggle
        const uploadTypeSelect = document.querySelector(SELECTORS.uploadType);
        if (uploadTypeSelect) {
            uploadTypeSelect.addEventListener('change', () => this.toggleUploadFields());
        }

        // Profile dropdown toggle
        const profileToggle = document.querySelector(SELECTORS.profileToggle);
        const dropdownMenu = document.querySelector(SELECTORS.dropdownMenu);
        
        if (profileToggle && dropdownMenu) {
            profileToggle.addEventListener('click', () => this.toggleProfileDropdown(dropdownMenu));
            
            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!profileToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
                    this.closeProfileDropdown(dropdownMenu);
                }
            });
        }

        // Modal handlers
        this.initializeModalHandlers();
    }

    // Toggle between file and link upload fields
    toggleUploadFields() {
        const uploadType = document.querySelector(SELECTORS.uploadType).value;
        const fileField = document.querySelector(SELECTORS.fileField);
        const linkField = document.querySelector(SELECTORS.linkField);
        const fileInput = document.querySelector(SELECTORS.fileInput);
        const linkInput = document.querySelector(SELECTORS.linkInput);
        
        if (uploadType === 'file') {
            this.showFileUpload(fileField, linkField, fileInput, linkInput);
        } else {
            this.showLinkUpload(fileField, linkField, fileInput, linkInput);
        }
    }

    // Show file upload fields
    showFileUpload(fileField, linkField, fileInput, linkInput) {
        fileField.classList.remove('hidden');
        linkField.classList.add('hidden');
        fileInput.required = true;
        linkInput.required = false;
    }

    // Show link upload fields
    showLinkUpload(fileField, linkField, fileInput, linkInput) {
        fileField.classList.add('hidden');
        linkField.classList.remove('hidden');
        fileInput.required = false;
        linkInput.required = true;
    }

    // Toggle profile dropdown
    toggleProfileDropdown(dropdownMenu) {
        dropdownMenu.classList.toggle('hidden');
        setTimeout(() => {
            dropdownMenu.classList.toggle('opacity-0');
        }, 50);
    }

    // Close profile dropdown
    closeProfileDropdown(dropdownMenu) {
        dropdownMenu.classList.add('hidden', 'opacity-0');
    }

    // Initialize modal handlers
    initializeModalHandlers() {
        // Open modal
        const openModalButtons = document.querySelectorAll('[data-action="open-modal"]');
        openModalButtons.forEach(button => {
            button.addEventListener('click', () => {
                const modalId = button.dataset.target;
                document.querySelector(modalId).classList.remove('hidden');
            });
        });

        // Close modal
        const closeModalButtons = document.querySelectorAll('[data-action="close-modal"]');
        closeModalButtons.forEach(button => {
            button.addEventListener('click', () => {
                const modalId = button.dataset.target;
                document.querySelector(modalId).classList.add('hidden');
            });
        });

        // Close modal on outside click
        const modals = document.querySelectorAll('.modal-backdrop');
        modals.forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                }
            });
        });
    }

    // Validate file before upload
    validateFile(file) {
        const maxSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png'];
        
        const extension = file.name.split('.').pop().toLowerCase();
        
        if (!allowedTypes.includes(extension)) {
            alert('Tipe file tidak diizinkan');
            return false;
        }
        
        if (file.size > maxSize) {
            alert('Ukuran file maksimal 5MB');
            return false;
        }
        
        return true;
    }

    // Validate link
    validateLink(url) {
        try {
            new URL(url);
            return true;
        } catch {
            alert('URL tidak valid');
            return false;
        }
    }
}

// Initialize Document Manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.documentManager = new DocumentManager();
});