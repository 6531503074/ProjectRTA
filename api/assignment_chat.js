// Assignment Chat Manager - Real-time
class AssignmentChatManager {
    constructor() {
        this.activeChats = new Map(); // Track active chats
        this.pollIntervals = new Map(); // Store poll intervals
        this.lastMessageIds = new Map(); // Track last message ID for each assignment
    }

    // Toggle chat visibility
    toggleChat(assignmentId) {
        const chatDiv = document.getElementById(`chat-${assignmentId}`);
        const wasVisible = chatDiv.classList.contains('show');
        
        chatDiv.classList.toggle('show');

        if (chatDiv.classList.contains('show') && !wasVisible) {
            // Chat was just opened
            this.activeChats.set(assignmentId, true);
            this.loadMessages(assignmentId, true);
            this.startPolling(assignmentId);
        } else if (!chatDiv.classList.contains('show')) {
            // Chat was closed
            this.activeChats.delete(assignmentId);
            this.stopPolling(assignmentId);
        }
    }

    // Load messages for an assignment
    async loadMessages(assignmentId, isInitialLoad = false) {
        try {
            const lastId = this.lastMessageIds.get(assignmentId) || 0;
            const url = `../api/assignment_chat_api.php?action=get_messages&assignment_id=${assignmentId}&last_id=${lastId}`;
            
            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                if (isInitialLoad) {
                    // Initial load - show all messages
                    this.displayMessages(assignmentId, data.messages, true);
                    
                    // Set last message ID
                    if (data.messages.length > 0) {
                        this.lastMessageIds.set(
                            assignmentId, 
                            data.messages[data.messages.length - 1].id
                        );
                    }
                } else if (data.messages.length > 0) {
                    // New messages received
                    this.displayMessages(assignmentId, data.messages, false);
                    
                    // Update last message ID
                    this.lastMessageIds.set(
                        assignmentId, 
                        data.messages[data.messages.length - 1].id
                    );
                    
                    // Update message count in toggle
                    this.updateMessageCount(assignmentId);
                }
            }
        } catch (error) {
            console.error('Error loading messages:', error);
        }
    }

    // Display messages
    displayMessages(assignmentId, messages, clearFirst = false) {
        const chatDiv = document.getElementById(`chat-${assignmentId}`);
        const emptyState = chatDiv.querySelector('.empty-state');
        const inputContainer = chatDiv.querySelector('.chat-input-container');

        if (messages.length === 0 && clearFirst) {
            emptyState.style.display = 'block';
            return;
        }

        emptyState.style.display = 'none';

        // Get or create messages container
        let messagesContainer = chatDiv.querySelector('.messages-list');
        if (!messagesContainer) {
            messagesContainer = document.createElement('div');
            messagesContainer.className = 'messages-list';
            chatDiv.insertBefore(messagesContainer, inputContainer);
        }

        if (clearFirst) {
            messagesContainer.innerHTML = '';
        }

        // Check if we should scroll to bottom
        const shouldScroll = this.isNearBottom(messagesContainer) || clearFirst;

        // Add messages
        messages.forEach(msg => {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'chat-message';
            messageDiv.style.animation = 'fadeIn 0.3s ease';
            messageDiv.innerHTML = `
                <div class="sender">
                    ${this.escapeHtml(msg.name)} 
                    <span style="color: #a0aec0; font-weight: normal; font-size: 11px;">
                        (${msg.role})
                    </span>
                </div>
                <div class="message">${this.escapeHtml(msg.message)}</div>
                <div class="time">${this.formatMessageTime(msg.created_at)}</div>
            `;
            messagesContainer.appendChild(messageDiv);
        });

        // Scroll to bottom if needed
        if (shouldScroll) {
            setTimeout(() => {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }, 100);
        }
    }

    // Check if user is near bottom of messages
    isNearBottom(container) {
        if (!container) return true;
        const threshold = 100;
        return container.scrollHeight - container.scrollTop - container.clientHeight < threshold;
    }

    // Send message
    async sendMessage(assignmentId) {
        const input = document.getElementById(`chat-input-${assignmentId}`);
        const message = input.value.trim();

        if (!message) return;

        try {
            const response = await fetch('../api/assignment_chat_api.php?action=send_message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    assignment_id: assignmentId,
                    message: message
                })
            });

            const data = await response.json();

            if (data.success) {
                input.value = '';
                
                // Update last message ID to prevent duplicate
                this.lastMessageIds.set(assignmentId, data.message.id);
                
                // Display the sent message immediately
                this.displayMessages(assignmentId, [data.message], false);
                
                // Update message count
                this.updateMessageCount(assignmentId);
            } else {
                alert(data.message || 'Failed to send message');
            }
        } catch (error) {
            console.error('Error sending message:', error);
            alert('Failed to send message');
        }
    }

    // Start polling for new messages
    startPolling(assignmentId) {
        // Clear existing interval if any
        this.stopPolling(assignmentId);

        // Poll every 2 seconds
        const interval = setInterval(() => {
            if (this.activeChats.has(assignmentId)) {
                this.loadMessages(assignmentId, false);
            } else {
                this.stopPolling(assignmentId);
            }
        }, 2000);

        this.pollIntervals.set(assignmentId, interval);
    }

    // Stop polling
    stopPolling(assignmentId) {
        const interval = this.pollIntervals.get(assignmentId);
        if (interval) {
            clearInterval(interval);
            this.pollIntervals.delete(assignmentId);
        }
    }

    // Update message count in the toggle button
    async updateMessageCount(assignmentId) {
        try {
            const response = await fetch(`../api/assignment_chat_api.php?action=get_message_count&assignment_id=${assignmentId}`);
            const data = await response.json();
            
            if (data.success) {
                const toggle = document.querySelector(`[onclick*="toggleAssignmentChat(${assignmentId})"]`);
                if (toggle) {
                    toggle.innerHTML = `💬 Assignment Discussion (${data.count} messages) <span class="chat-status"></span>`;
                }
            }
        } catch (error) {
            console.error('Error updating message count:', error);
        }
    }

    // Stop all polling (call when leaving page)
    stopAllPolling() {
        this.pollIntervals.forEach((interval, assignmentId) => {
            this.stopPolling(assignmentId);
        });
        this.activeChats.clear();
    }

    // Utility functions
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    formatMessageTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;

        // Less than 1 minute
        if (diff < 60000) return 'Just now';
        
        // Less than 1 hour
        if (diff < 3600000) {
            const minutes = Math.floor(diff / 60000);
            return `${minutes}m ago`;
        }
        
        // Less than 24 hours
        if (diff < 86400000) {
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
        
        // Older
        return date.toLocaleString([], { 
            month: 'short', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit' 
        });
    }
}

// Initialize the assignment chat manager
const assignmentChatManager = new AssignmentChatManager();

// Global functions for HTML onclick attributes
function toggleAssignmentChat(assignmentId) {
    assignmentChatManager.toggleChat(assignmentId);
}

function sendAssignmentMessage(assignmentId) {
    assignmentChatManager.sendMessage(assignmentId);
}

// Allow Enter key to send message
document.addEventListener('DOMContentLoaded', function() {
    // Add enter key listener for all chat inputs
    document.addEventListener('keypress', function(e) {
        if (e.target.id && e.target.id.startsWith('chat-input-')) {
            if (e.key === 'Enter') {
                const assignmentId = e.target.id.replace('chat-input-', '');
                sendAssignmentMessage(parseInt(assignmentId));
            }
        }
    });
});

// Clean up when leaving page
window.addEventListener('beforeunload', function() {
    assignmentChatManager.stopAllPolling();
});