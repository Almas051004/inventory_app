import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'preview'];

    connect() {
        this.updatePreview();
    }

    updatePreview() {
        const url = this.inputTarget.value.trim();
        const previewContainer = this.previewTarget;

        if (!url) {
            previewContainer.innerHTML = '';
            return;
        }

        // Определяем тип ссылки
        const extension = this.getFileExtension(url);

        if (this.isImageExtension(extension)) {
            this.showImagePreview(url, previewContainer);
        } else if (extension === 'pdf') {
            this.showPdfPreview(url, previewContainer);
        } else {
            this.showLinkPreview(url, previewContainer);
        }
    }

    getFileExtension(url) {
        try {
            const urlObj = new URL(url);
            const pathname = urlObj.pathname;
            return pathname.split('.').pop().toLowerCase();
        } catch (e) {
            return '';
        }
    }

    isImageExtension(extension) {
        const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
        return imageExtensions.includes(extension);
    }

    showImagePreview(url, container) {
        container.innerHTML = `
            <div class="link-preview image-preview mb-2">
                <a href="${this.escapeHtml(url)}" target="_blank" rel="noopener noreferrer" class="d-block">
                    <img src="${this.escapeHtml(url)}" alt="Image preview" class="img-thumbnail" style="max-width: 200px; max-height: 150px;" onerror="this.style.display='none'">
                </a>
                <small class="text-muted d-block">${this.escapeHtml(url)}</small>
            </div>
        `;
    }

    showPdfPreview(url, container) {
        container.innerHTML = `
            <div class="link-preview pdf-preview mb-2">
                <a href="${this.escapeHtml(url)}" target="_blank" rel="noopener noreferrer" class="d-block">
                    <div class="pdf-preview-icon d-flex align-items-center p-3 border rounded">
                        <i class="bi bi-file-earmark-pdf-fill text-danger me-3" style="font-size: 2rem;"></i>
                        <div>
                            <div class="fw-bold">PDF Document</div>
                            <small class="text-muted">Click to open</small>
                        </div>
                    </div>
                </a>
                <small class="text-muted d-block">${this.escapeHtml(url)}</small>
            </div>
        `;
    }

    showLinkPreview(url, container) {
        container.innerHTML = `
            <a href="${this.escapeHtml(url)}" target="_blank" rel="noopener noreferrer" class="text-decoration-none">${this.escapeHtml(url)}</a>
        `;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Обработчик события input
    onInput() {
        // Добавляем debounce для предотвращения слишком частых обновлений
        clearTimeout(this.timeout);
        this.timeout = setTimeout(() => {
            this.updatePreview();
        }, 300);
    }
}
