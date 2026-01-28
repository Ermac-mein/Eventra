// Admin Authentication and Profile Management
class AdminAuth {
    constructor() {
        this.adminData = null;
        this.profilePicCache = null;
    }

    async loadAdminProfile() {
        try {
            const response = await fetch('../../api/admin/get-profile.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error('Failed to fetch admin profile');
            }

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

        // Update header avatar with profile picture from database or default
        const headerAvatar = document.querySelector('.user-avatar');
        if (headerAvatar) {
            const profilePic = this.adminData.profile_pic || '/public/assets/imgs/admin.png';
            headerAvatar.style.backgroundImage = `url(${profilePic})`;
            headerAvatar.style.backgroundSize = 'cover';
            headerAvatar.style.backgroundPosition = 'center';
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

        // Update profile avatar in drawer with database profile_pic or default
        const profileAvatar = profileDrawer.querySelector('.profile-avatar-large');
        if (profileAvatar) {
            const profilePic = this.adminData.profile_pic || '/public/assets/imgs/admin.png';
            profileAvatar.src = profilePic;
            profileAvatar.alt = this.adminData.name;
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
        `;
    }

    async handleLogout() {
        try {
            const response = await fetch('../../api/auth/logout.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include'
            });

            const result = await response.json();
            
            if (result.success) {
                // Clear local storage
                if (typeof storage !== 'undefined') {
                    storage.remove('user');
                    storage.remove('auth_token');
                }
                
                // Show success message
                if (window.toast) {
                    window.toast.success('Logged out successfully');
                }
                
                // Redirect to login
                setTimeout(() => {
                    window.location.href = '../../public/pages/login.html';
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
