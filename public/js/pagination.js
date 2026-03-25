/**
 * Modern HD Pagination Component Logic
 * Optimized for Eventra Tables
 */

class EventraPagination {
    constructor(options) {
        this.mode = options.mode || 'client'; // 'client' or 'server'
        this.data = options.data || [];
        this.pageSize = options.pageSize || 10;
        this.persistState = options.persistState || false;
        this.containerId = options.containerId;
        this.renderTableCallback = options.onPageChange;
        this.selectedIds = new Set(options.selectedIds || []);
        this.totalItems = options.totalItems || this.data.length;
        this.totalPages = options.totalPages || Math.ceil(this.totalItems / this.pageSize);

        if (this.persistState) {
            const urlParams = new URLSearchParams(window.location.search);
            const urlPage = parseInt(urlParams.get('page'));
            if (!isNaN(urlPage) && urlPage > 0) {
                this.currentPage = urlPage;
            } else {
                this.currentPage = options.currentPage || 1;
            }
        } else {
            this.currentPage = options.currentPage || 1;
        }

        this.init();
    }

    init() {
        this.render();
    }

    updateData(newData, totalItems, totalPages, currentPage, refreshTable = true) {
        this.data = newData || [];
        if (this.mode === 'server') {
            this.totalItems = totalItems ?? this.totalItems;
            this.totalPages = totalPages ?? Math.ceil(this.totalItems / this.pageSize);
            this.currentPage = currentPage ?? this.currentPage;
        } else {
            this.totalItems = this.data.length;
            this.totalPages = Math.ceil(this.totalItems / this.pageSize);
            // Stay on current page if within bounds, otherwise go to last valid page
            if (this.currentPage > this.totalPages && this.totalPages > 0) {
                this.currentPage = this.totalPages;
            } else if (this.totalPages === 0) {
                this.currentPage = 1;
            }
        }
        
        this.render();
        
        if (refreshTable && this.renderTableCallback) {
            this.renderTableCallback(this.getPageData(), false);
        }
    }

    refresh() {
        this.render();
        if (this.renderTableCallback) {
            this.renderTableCallback(this.getPageData(), false);
        }
    }

    setPage(page, smoothScroll = true) {
        if (page < 1 || (this.totalPages > 0 && page > this.totalPages)) return;
        this.currentPage = page;
        
        if (this.persistState) {
            this.syncUrl();
        }

        this.render();
        if (this.renderTableCallback) {
            this.renderTableCallback(this.getPageData(), smoothScroll);
        }
    }

    syncUrl() {
        const url = new URL(window.location.href);
        url.searchParams.set('page', this.currentPage);
        window.history.replaceState({ page: this.currentPage }, '', url.toString());
    }

    setPageSize(size) {
        this.pageSize = parseInt(size);
        this.totalPages = Math.ceil(this.totalItems / this.pageSize);
        this.setPage(1); // Reset to page 1 when size changes
    }

    getPageData() {
        const start = (this.currentPage - 1) * this.pageSize;
        const end = start + this.pageSize;
        return this.data.slice(start, end);
    }

    toggleSelection(id, isSelected) {
        if (isSelected) {
            this.selectedIds.add(id);
        } else {
            this.selectedIds.delete(id);
        }
    }

    getSelectedIds() {
        return Array.from(this.selectedIds);
    }

    render() {
        const container = document.getElementById(this.containerId);
        if (!container) return;

        const startItem = this.totalItems === 0 ? 0 : (this.currentPage - 1) * this.pageSize + 1;
        const endItem = Math.min(this.currentPage * this.pageSize, this.totalItems);

        let paginationHtml = `
            <div class="pagination-bar">
                <div class="pagination-left">
                    <span>Rows per page:</span>
                    <div class="rows-per-page-container">
                        <select class="rows-per-page-select" id="pg-rows-select">
                            <option value="10" ${this.pageSize === 10 ? 'selected' : ''}>10</option>
                            <option value="25" ${this.pageSize === 25 ? 'selected' : ''}>25</option>
                            <option value="50" ${this.pageSize === 50 ? 'selected' : ''}>50</option>
                            <option value="100" ${this.pageSize === 100 ? 'selected' : ''}>100</option>
                        </select>
                    </div>
                </div>
                
                <div class="pagination-info">
                    Showing ${startItem}-${endItem} of ${this.totalItems.toLocaleString()}
                </div>
                
                <div class="pagination-controls">
                    <button class="pg-btn ${this.currentPage === 1 ? 'disabled' : ''}" 
                            ${this.currentPage === 1 ? 'disabled' : ''} 
                            id="pg-prev" title="Previous Page">
                        <i data-lucide="chevron-left" class="pg-nav-icon"></i>
                    </button>
                    
                    ${this.renderPageNumbers()}
                    
                    <button class="pg-btn ${this.currentPage === this.totalPages || this.totalPages <= 1 ? 'disabled' : ''}" 
                            ${this.currentPage === this.totalPages || this.totalPages <= 1 ? 'disabled' : ''} 
                            id="pg-next" title="Next Page">
                        <i data-lucide="chevron-right" class="pg-nav-icon"></i>
                    </button>
                </div>
            </div>
        `;

        container.innerHTML = paginationHtml;

        // Re-initialize Lucide Icons if available
        if (window.lucide) {
            window.lucide.createIcons();
        }

        this.addEventListeners();
    }

    renderPageNumbers() {
        let html = '';
        const maxVisible = 5;
        let start = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
        let end = Math.min(this.totalPages, start + maxVisible - 1);

        if (end - start + 1 < maxVisible) {
            start = Math.max(1, end - maxVisible + 1);
        }

        if (start > 1) {
            html += `<button class="pg-btn pg-num" data-page="1">1</button>`;
            if (start > 2) html += `<span style="padding: 0 4px; color: var(--pg-text-muted);">...</span>`;
        }

        for (let i = start; i <= end; i++) {
            html += `<button class="pg-btn pg-num ${i === this.currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }

        if (end < this.totalPages) {
            if (end < this.totalPages - 1) html += `<span style="padding: 0 4px; color: var(--pg-text-muted);">...</span>`;
            html += `<button class="pg-btn pg-num" data-page="${this.totalPages}">${this.totalPages}</button>`;
        }

        return html;
    }

    addEventListeners() {
        const container = document.getElementById(this.containerId);
        if (!container) return;

        container.querySelector('#pg-rows-select').addEventListener('change', (e) => {
            this.setPageSize(e.target.value);
            if (this.renderTableCallback) {
                this.renderTableCallback(this.getPageData());
            }
        });

        const prevBtn = container.querySelector('#pg-prev');
        if (prevBtn) {
            prevBtn.addEventListener('click', () => this.setPage(this.currentPage - 1));
        }

        const nextBtn = container.querySelector('#pg-next');
        if (nextBtn) {
            nextBtn.addEventListener('click', () => this.setPage(this.currentPage + 1));
        }

        container.querySelectorAll('.pg-num').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.setPage(parseInt(e.target.dataset.page));
            });
        });
    }
}

window.EventraPagination = EventraPagination;
