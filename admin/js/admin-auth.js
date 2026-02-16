// Admin Authentication and Profile Management
class AdminAuth {
    constructor() {
        this.adminData = null;
        this.profilePicCache = null;
    }

    async loadAdminProfile() {
        try {
            const response = await apiFetch('../../api/admin/get-profile.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const result = await response.json();
            
            if (result.success) {
                this.adminData = result.admin;
                this.updateProfileUI();
                return this.adminData;
            } else {
                console.error('Failed to load admin profile:', result.message);
                return null;
            }
        } catch (error) {
            console.error('Error loading admin profile:', error);
            return null;
        }
    }

    updateProfileUI() {
        if (!this.adminData) return;

        // Update header avatar with profile picture from database or default admin avatar
        const headerAvatar = document.querySelector('.user-avatar-display, .user-avatar');
        if (headerAvatar) {
            const profilePic = '../../public/assets/imgs/admin.png';
            headerAvatar.style.backgroundImage = `url(${profilePic})`;
            headerAvatar.style.backgroundSize = 'cover';
            headerAvatar.style.backgroundPosition = 'center';
        }

        // Update header name display
        const headerName = document.querySelector('.user-name-display');
        if (headerName) {
            headerName.textContent = this.adminData.name;
        }

        // Update profile drawer
        this.updateProfileDrawer();
    }

    updateProfileDrawer() {
        if (!this.adminData) return;

        const profileDrawer = document.getElementById('profileDrawer');
        if (!profileDrawer) return;

        // Update profile drawer header
        const drawerTitle = profileDrawer.querySelector('.drawer-header h2');
        if (drawerTitle) {
            drawerTitle.textContent = 'Admin Profile';
        }

        // Update profile avatar in drawer with database profile_pic or default admin avatar
        const profileAvatar = profileDrawer.querySelector('.profile-avatar-large');
        if (profileAvatar) {
            const profilePic = '../../public/assets/imgs/admin.png';
            profileAvatar.style.backgroundImage = `url(${profilePic})`;
            profileAvatar.style.backgroundSize = 'cover';
            profileAvatar.style.backgroundPosition = 'center';
            // Also set as img src if it's an img element
            if (profileAvatar.tagName === 'IMG') {
                profileAvatar.src = profilePic;
            }
        }

        // Update or create profile main info
        let profileMainInfo = profileDrawer.querySelector('.profile-main-info');
        if (!profileMainInfo) {
            profileMainInfo = document.createElement('div');
            profileMainInfo.className = 'profile-main-info';
            const avatarWrapper = profileDrawer.querySelector('.profile-avatar-wrapper');
            if (avatarWrapper) {
                avatarWrapper.after(profileMainInfo);
            }
        }

        profileMainInfo.innerHTML = `
            <h3 class="profile-name">${this.adminData.name}</h3>
            <p class="profile-email">${this.adminData.email}</p>
        `;

        // Update or create profile details
        let profileDetails = profileDrawer.querySelector('.profile-details-list');
        if (!profileDetails) {
            profileDetails = document.createElement('div');
            profileDetails.className = 'profile-details-list';
            profileMainInfo.after(profileDetails);
        }

        const createdDate = new Date(this.adminData.created_at).toLocaleDateString();
        const updatedDate = new Date(this.adminData.updated_at).toLocaleDateString();

        profileDetails.innerHTML = `
            <div class="profile-detail-item">
                <span class="detail-label">Role</span>
                <span class="detail-value">Administrator</span>
            </div>
            <div class="profile-detail-item">
                <span class="detail-label">Status</span>
                <span class="detail-value">${this.adminData.status === 'active' ? 'Active' : 'Offline'}</span>
            </div>
            <div class="profile-detail-item">
                <span class="detail-label">Account Created</span>
                <span class="detail-value">${createdDate}</span>
            </div>
            <div class="profile-detail-item">
                <span class="detail-label">Last Updated</span>
                <span class="detail-value">${updatedDate}</span>
            </div>
            <button class="btn btn-logout-drawer" id="profileDrawerLogout" style="margin-top: 2rem; width: 100%; justify-content: center; gap: 10px; color: white; background: #ef4444;">
                <i data-lucide="log-out"></i>
                <span>Logout</span>
            </button>
        `;

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    async handleLogout() {
        try {
            const response = await apiFetch('../../api/auth/logout.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const result = await response.json();
            
            if (result.success) {
                // Clear local storage (namespaced for Admin)
                if (typeof storage !== 'undefined') {
                    storage.remove('admin_user');
                    storage.remove('admin_auth_token');
                }
                
                // Show success message
                if (window.toast) {
                    window.toast.success('Logged out successfully');
                }
                
                // Redirect to login
                setTimeout(() => {
                    window.location.href = '../../admin/pages/adminLogin.html';
                }, 1000);
            } else {
                if (window.toast) {
                    window.toast.error(result.message || 'Logout failed');
                }
            }
        } catch (error) {
            console.error('Logout error:', error);
            if (window.toast) {
                window.toast.error('An error occurred during logout');
            }
        }
    }
}

// Create global instance
window.adminAuth = new AdminAuth();
