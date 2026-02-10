
// Event Preview Function for Admin
async function previewEvent(eventId) {
    const row = document.querySelector(`tr[data-id="${eventId}"]`);
    if (!row) return;

    const eventName = row.cells[0].innerText;
    const state = row.cells[1].innerText;
    const clientName = row.cells[2].innerText;
    const price = row.cells[3].innerText;
    const attendees = row.dataset.attendees || row.cells[4].innerText;
    const category = row.dataset.category || row.cells[5].innerText;
    const status = row.cells[6].innerText.trim();
    const eventImage = row.dataset.image || 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1200&fit=crop';
    const tag = row.dataset.tag;
    const description = row.dataset.description;
    const address = row.dataset.address;
    const date = row.dataset.date;
    const time = row.dataset.time;
    const priority = row.dataset.priority;
    const phone = row.dataset.phone;

    // Create Modal Backdrop (if not exists)
    let backdrop = document.querySelector('.preview-modal-backdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = 'preview-modal-backdrop';
        backdrop.setAttribute('role', 'dialog');
        backdrop.setAttribute('aria-modal', 'true');
        backdrop.setAttribute('aria-hidden', 'false');
        backdrop.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 1000; backdrop-filter: blur(4px); transition: all 0.3s ease;';
        backdrop.innerHTML = `
            <div class="preview-modal" style="background: white; width: 95%; max-width: 650px; border-radius: 16px; overflow: hidden; position: relative; transform: translateY(20px); transition: all 0.3s ease; box-shadow: 0 20px 40px rgba(0,0,0,0.2); max-height: 90vh; display: flex; flex-direction: column;">
                <button class="preview-close" aria-label="Close Preview" style="position: absolute; top: 1rem; right: 1rem; background: rgba(255,255,255,0.8); border: none; width: 32px; height: 32px; border-radius: 50%; font-size: 1.5rem; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 10; box-shadow: 0 2px 8px rgba(0,0,0,0.1); backdrop-filter: blur(4px);">×</button>
                <div id="previewContent" style="overflow-y: auto; flex: 1;"></div>
            </div>
        `;
        document.body.appendChild(backdrop);

        const closeBtn = backdrop.querySelector('.preview-close');
        closeBtn.onclick = () => {
            backdrop.style.opacity = '0';
            backdrop.querySelector('.preview-modal').style.transform = 'translateY(20px)';
            setTimeout(() => { backdrop.style.display = 'none'; }, 300);
        };
        backdrop.onclick = (e) => {
            if (e.target === backdrop) closeBtn.click();
        };
    }

    const content = backdrop.querySelector('#previewContent');
    const statusColor = status.toLowerCase() === 'published' ? '#10b981' : status.toLowerCase() === 'scheduled' ? '#3b82f6' : '#ef4444';
    
    content.innerHTML = `
        <div class="event-preview">
            <div style="height: 250px; overflow: hidden; position: relative;">
                <img src="${eventImage}" style="width: 100%; height: 100%; object-fit: cover;" alt="Event">
                <div style="position: absolute; top: 1rem; left: 1rem; background: ${statusColor}; color: white; padding: 0.5rem 1rem; border-radius: 30px; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                    ${status}
                </div>
            </div>
            <div style="padding: 2rem;">
                <div style="margin-bottom: 2rem;">
                    <h1 style="font-size: 1.85rem; font-weight: 800; color: #111827; line-height: 1.2; margin-bottom: 0.5rem;">${eventName}</h1>
                    <p style="color: #6b7280; font-weight: 600;">by ${clientName}</p>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 40px; height: 40px; background: #eef2ff; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">📅</div>
                        <div>
                            <div style="font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase;">Date</div>
                            <div style="font-weight: 700; color: #374151;">${new Date(date).toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' })}</div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 40px; height: 40px; background: #fff7ed; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">🕒</div>
                        <div>
                            <div style="font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase;">Time</div>
                            <div style="font-weight: 700; color: #374151;">${time.substring(0, 5)}</div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 40px; height: 40px; background: #f0fdf4; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">🎟️</div>
                        <div>
                            <div style="font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase;">Price</div>
                            <div style="font-weight: 700; color: #374151;">${price}</div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 40px; height: 40px; background: #fdf2f8; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">📂</div>
                        <div>
                            <div style="font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase;">Category</div>
                            <div style="font-weight: 700; color: #374151;">${category}</div>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 2rem;">
                    <label style="display: block; font-size: 0.85rem; color: #111827; margin-bottom: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">📍 Location & Address</label>
                    <div style="background: #f9fafb; padding: 1rem; border-radius: 12px; border: 1px solid #e5e7eb; color: #4b5563; font-weight: 500;">
                        ${address || state || 'No address provided'}
                    </div>
                </div>

                <div style="margin-bottom: 2rem;">
                    <label style="display: block; font-size: 0.85rem; color: #111827; margin-bottom: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">📝 Description</label>
                    <div style="color: #4b5563; line-height: 1.6; white-space: pre-wrap; background: #f9fafb; padding: 1rem; border-radius: 12px; border: 1px solid #e5e7eb;">${description || 'No description available'}</div>
                </div>

                <div style="margin-bottom: 2rem;">
                    <label style="display: block; font-size: 0.85rem; color: #111827; margin-bottom: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">👥 Attendees</label>
                    <div style="display: flex; align-items: center; gap: 15px; background: #f9fafb; padding: 1rem; border-radius: 12px; border: 1px solid #e5e7eb;">
                        <span style="font-size: 1rem; color: #111827; font-weight: 700;">${attendees} people attending</span>
                    </div>
                </div>
                
                ${phone ? `
                <div style="margin-bottom: 2rem;">
                    <label style="display: block; font-size: 0.85rem; color: #111827; margin-bottom: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">📞 Contact</label>
                    <div style="background: #f9fafb; padding: 1rem; border-radius: 12px; border: 1px solid #e5e7eb; color: #4b5563; font-weight: 500;">
                        ${phone}
                    </div>
                </div>
                ` : ''}
            </div>
        </div>
    `;

    backdrop.style.display = 'flex';
    backdrop.style.opacity = '0';
    setTimeout(() => {
        backdrop.style.opacity = '1';
        backdrop.querySelector('.preview-modal').style.transform = 'translateY(0)';
    }, 10);
}

// Make function globally available
window.previewEvent = previewEvent;
