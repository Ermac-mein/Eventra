/**
 * Paystack Banks Helper
 * Fetches and populates bank dropdowns for account verification.
 */

const PaystackBanks = {
    banks: [],
    isLoaded: false,
    CACHE_KEY: 'paystack_banks_cache',
    CACHE_TTL: 24 * 60 * 60 * 1000, // 24 hours in ms

    async load() {
        if (this.isLoaded) return this.banks;

        // Try Loading from LocalStorage first
        const cached = localStorage.getItem(this.CACHE_KEY);
        if (cached) {
            try {
                const { data, timestamp } = JSON.parse(cached);
                if (Date.now() - timestamp < this.CACHE_TTL) {
                    this.banks = data;
                    this.isLoaded = true;
                    return this.banks;
                }
            } catch (e) {
                console.warn('Failed to parse bank cache');
            }
        }

        try {
            const res = await apiFetch('../../api/clients/get-banks.php');
            const data = await res.json();
            if (data.success) {
                this.banks = data.banks;
                this.isLoaded = true;
                
                // Save to LocalStorage
                localStorage.setItem(this.CACHE_KEY, JSON.stringify({
                    data: this.banks,
                    timestamp: Date.now()
                }));

                return this.banks;
            }
        } catch (err) {
            console.error('Failed to load banks:', err);
        }
        return [];
    },

    async populate(selectElement, currentValue = '') {
        const banks = await this.load();
        if (!banks || banks.length === 0) {
            selectElement.innerHTML = '<option value="">Select Bank (Error Loading)</option>';
            return;
        }
        selectElement.innerHTML = '<option value="">Select Bank</option>' + 
            banks.map(b => `<option value="${b.code}" ${String(b.code) === String(currentValue) ? 'selected' : ''}>${b.name}</option>`).join('');
    }
};

window.PaystackBanks = PaystackBanks;
