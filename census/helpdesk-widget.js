
class HelpdeskWidget {
    constructor() {
        this.userId = 1; // In real system, get from session
        this.init();
    }
    
    init() {
        this.createWidget();
        this.bindEvents();
        this.loadFAQs('all');
    }
    
    createWidget() {
        const widgetHTML = `
        <div class="helpdesk-widget">
            <button class="helpdesk-toggle">💬 Help</button>
            <div class="helpdesk-panel">
                <div class="helpdesk-header">
                    <h3>Support Center</h3>
                    <button class="close-helpdesk">×</button>
                </div>
                <div class="helpdesk-content">
                    <div class="tab-buttons">
                        <button class="tab-btn active" data-tab="faqs">FAQs</button>
                        <button class="tab-btn" data-tab="contact">Contact</button>
                        <button class="tab-btn" data-tab="chat">Live Chat</button>
                    </div>
                    
                    <div class="tab-content">
                        <div class="tab-pane active" id="faqs-tab">
                            <div class="faq-categories">
                                <button class="category-btn active" data-category="all">All</button>
                                <button class="category-btn" data-category="account">Account</button>
                                <button class="category-btn" data-category="registration">Registration</button>
                                <button class="category-btn" data-category="data">Data</button>
                            </div>
                            <div class="faqs-list" id="faqs-list">
                                <div class="loading-text">Loading FAQs...</div>
                            </div>
                        </div>
                        
                        <div class="tab-pane" id="contact-tab">
                            <form id="support-form">
                                <input type="text" name="subject" placeholder="Subject" required>
                                <textarea name="message" placeholder="Describe your issue..." required></textarea>
                                <select name="priority">
                                    <option value="low">Low Priority</option>
                                    <option value="medium" selected>Medium Priority</option>
                                    <option value="high">High Priority</option>
                                </select>
                                <button type="submit">Submit Ticket</button>
                            </form>
                            <div id="form-message"></div>
                        </div>
                        
                        <div class="tab-pane" id="chat-tab">
                            <div class="chat-messages" id="chat-messages">
                                <div class="system-message">Live chat support will be available during business hours (8 AM - 5 PM).</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', widgetHTML);
    }
    
    bindEvents() {

        document.querySelector('.helpdesk-toggle').addEventListener('click', () => {
            document.querySelector('.helpdesk-panel').classList.toggle('active');
        });
        
        
        document.querySelector('.close-helpdesk').addEventListener('click', () => {
            document.querySelector('.helpdesk-panel').classList.remove('active');
        });
        
        
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const tab = e.target.dataset.tab;
                this.switchTab(tab);
            });
        });
        
    
        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const category = e.target.dataset.category;
                this.filterFAQs(category);
            });
        });
        
        
        document.getElementById('support-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitSupportTicket();
        });
    }
    
    switchTab(tabName) {
        
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
        
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('active');
        });
        document.querySelector(`#${tabName}-tab`).classList.add('active');
    }
    
    filterFAQs(category) {
        
        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`[data-category="${category}"]`).classList.add('active');
        
        this.loadFAQs(category);
    }
    
    async loadFAQs(category = 'all') {
        const faqsList = document.getElementById('faqs-list');
        faqsList.innerHTML = '<div class="loading-text">Loading FAQs...</div>';
        
        try {
            const url = category === 'all' ? 'get-faqs.php' : `get-faqs.php?category=${category}`;
            const response = await fetch(url);
            const result = await response.json();
            
            if (result.success) {
                if (result.data.length === 0) {
                    faqsList.innerHTML = '<div class="no-faqs">No FAQs found for this category.</div>';
                } else {
                    faqsList.innerHTML = result.data.map(faq => `
                        <div class="faq-item">
                            <div class="faq-question">${faq.question}</div>
                            <div class="faq-answer">${faq.answer}</div>
                        </div>
                    `).join('');
                }
            } else {
                faqsList.innerHTML = `<div class="error-text">Error loading FAQs: ${result.error}</div>`;
            }
        } catch (error) {
            faqsList.innerHTML = `<div class="error-text">Failed to load FAQs. Please try again.</div>`;
            console.error('Error loading FAQs:', error);
        }
    }
    
    async submitSupportTicket() {
        const form = document.getElementById('support-form');
        const formData = new FormData(form);
        const messageDiv = document.getElementById('form-message');
        
        const ticketData = {
            user_id: this.userId,
            subject: formData.get('subject'),
            message: formData.get('message'),
            priority: formData.get('priority')
        };
        
        messageDiv.innerHTML = '<div class="loading-text">Submitting ticket...</div>';
        messageDiv.className = 'form-message';
        
        try {
            const response = await fetch('submit-ticket.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(ticketData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                messageDiv.innerHTML = `<div class="success-message">
                    ✅ ${result.message}<br>
                    <small>Reference: ${result.reference}</small>
                </div>`;
                messageDiv.className = 'form-message success';
                form.reset();
            } else {
                messageDiv.innerHTML = `<div class="error-message">❌ ${result.error}</div>`;
                messageDiv.className = 'form-message error';
            }
        } catch (error) {
            messageDiv.innerHTML = `<div class="error-message">❌ Network error. Please try again.</div>`;
            messageDiv.className = 'form-message error';
            console.error('Error submitting ticket:', error);
        }
    }
}


document.addEventListener('DOMContentLoaded', () => {
    new HelpdeskWidget();
});