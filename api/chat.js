// Real-time Chat Manager
class ChatManager {
    constructor(courseId, userId) {
        this.courseId = courseId;
        this.userId = userId;
        this.currentGroupId = null;
        this.lastMessageId = 0;
        this.pollInterval = null;
        this.isPolling = false;
        this.currentView = 'list'; // 'list' or 'chat'
    }

    // Load groups
    async loadGroups(filter = 'all') {
        try {
            const response = await fetch(`../api/chat_api.php?action=get_groups&course_id=${this.courseId}&filter=${filter}`);
            const data = await response.json();

            if (data.success) {
                this.isTeacher = data.is_teacher || false;
                this.displayGroups(data.groups, filter);
                this.currentView = 'list';
            }
        } catch (error) {
            console.error('Error loading groups:', error);
        }
    }

    // Display groups
    displayGroups(groups, filter) {
        const content = document.getElementById('chatContent');
        const isGlobal = !this.courseId; // Global view if no courseId

        let html = '';
        if (groups.length === 0) {
            html = `
                <div class="empty-state" style="padding: 60px 20px;">
                    <div class="empty-state-icon">💬</div>
                    <p>${filter === 'my' ? 'คุณยังไม่ได้เข้าร่วมกลุ่มใดๆ' : 'ยังไม่มีกลุ่มแชทในขณะนี้'}</p>
                </div>
            `;
        } else {
            groups.forEach(group => {
                const isMember = filter === 'my' || (group.is_member && group.is_member > 0);
                const courseTitle = group.course_title ? `<div style="font-size:11px; color:#667eea; margin-bottom:2px;">${this.escapeHtml(group.course_title)}</div>` : '';
                const unreadBadge = (group.unread_count && group.unread_count > 0) ? `<span class="unread-badge" style="background:#e74c3c; color:white; border-radius:50%; padding:2px 8px; font-size:12px; margin-left:8px;">${group.unread_count}</span>` : '';

                const deleteIcon = this.isTeacher ? `<div class="delete-group-icon" onclick="event.stopPropagation(); chatManager.deleteGroup(${group.id})" style="color: #e53e3e; cursor: pointer; font-size: 16px; padding: 4px;" title="Delete Group">🗑️</div>` : '';

                html += `
                    <div class="group-chat-item" onclick="chatManager.openGroup(${group.id})">
                        ${courseTitle}
                        <div class="name" style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                ${this.escapeHtml(group.name)}
                                ${unreadBadge}
                            </div>
                            ${deleteIcon}
                        </div>
                        <div class="members">
                            👥 ${group.member_count} สมาชิก | 💬 ${group.message_count} ข้อความ
                            ${!isMember ? ' | <span style="color: #667eea; font-weight: 600;">คลิกเพื่อเข้าร่วม</span>' : ''}
                        </div>
                    </div>
                `;
            });
        }

        html += `
            <button class="create-group-btn" onclick="chatManager.openCreateGroupModal()">
                ➕ สร้างกลุ่มใหม่
            </button>
        `;

        content.innerHTML = html;
    }

    // Open group chat
    async openGroup(groupId) {
        // Check if user is member, if not, join first
        const response = await fetch(`../api/chat_api.php?action=get_messages&group_id=${groupId}`);
        const data = await response.json();

        if (!data.success && data.message === 'ไม่ได้เป็นสมาชิกกลุ่ม') {
            if (confirm('คุณต้องเข้าร่วมกลุ่มนี้เพื่อดูข้อความ เข้าร่วมเลยไหม?')) {
                await this.joinGroup(groupId);
                this.openGroup(groupId); // Retry
            }
            return;
        }

        this.currentGroupId = groupId;
        this.lastMessageId = 0;
        this.currentView = 'chat';

        // Load group info
        const groupInfo = await this.getGroupInfo(groupId);

        // Show chat window
        this.showChatWindow(groupId, groupInfo);

        // Mark as read
        this.markAsRead(groupId);

        // Load messages
        await this.loadMessages();

        // Start polling for new messages
        this.startPolling();
    }

    // Get group info
    async getGroupInfo(groupId) {
        try {
            const response = await fetch(`../api/chat_api.php?action=get_group_info&group_id=${groupId}`);
            const data = await response.json();
            return data.success ? data.group : { name: 'แชทกลุ่ม' };
        } catch (error) {
            return { name: 'แชทกลุ่ม' };
        }
    }

    // Show chat window
    showChatWindow(groupId, groupInfo) {
        const chatWindow = document.getElementById('chatWindow') || this.createChatWindow();
        document.getElementById('chatWindowTitle').textContent = groupInfo.name || 'แชทกลุ่ม';
        chatWindow.style.display = 'flex';

        // Hide floating chat list
        document.getElementById('floatingChat').classList.remove('show');
    }

    // Create chat window
    createChatWindow() {
        const chatWindow = document.createElement('div');
        chatWindow.id = 'chatWindow';
        chatWindow.className = 'chat-window';
        chatWindow.innerHTML = `
            <div class="chat-window-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span onclick="chatManager.backToList()" style="cursor: pointer; font-size: 20px; opacity: 0.9;">←</span>
                    <h3 id="chatWindowTitle">แชทกลุ่ม</h3>
                </div>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <span onclick="chatManager.viewGroupInfo()" style="cursor: pointer; opacity: 0.8;">ℹ️</span>
                    <span class="chat-window-close" onclick="chatManager.closeChatWindow()">×</span>
                </div>
            </div>
            <div class="chat-messages-container" id="chatMessagesContainer"></div>
            <div class="chat-input-area">
                <input type="text" id="chatMessageInput" placeholder="พิมพ์ข้อความ..." 
                       onkeypress="if(event.key==='Enter') chatManager.sendMessage()">
                <button class="btn-send" onclick="chatManager.sendMessage()">ส่ง</button>
            </div>
        `;
        document.body.appendChild(chatWindow);

        // Add styles
        if (!document.getElementById('chatWindowStyles')) {
            const style = document.createElement('style');
            style.id = 'chatWindowStyles';
            style.textContent = `
                .chat-window {
                    position: fixed;
                    bottom: 30px;
                    right: 30px;
                    width: 400px;
                    height: 600px;
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 8px 24px rgba(0,0,0,0.3);
                    display: none;
                    flex-direction: column;
                    z-index: 10000;
                }
                .chat-messages-container {
                    flex: 1;
                    overflow-y: auto;
                    padding: 20px;
                    background: #f7fafc;
                }
                .chat-messages-container::-webkit-scrollbar {
                    width: 6px;
                }
                .chat-messages-container::-webkit-scrollbar-thumb {
                    background: #cbd5e0;
                    border-radius: 3px;
                }
                .chat-input-area {
                    padding: 15px;
                    border-top: 1px solid #e2e8f0;
                    display: flex;
                    gap: 10px;
                    background: white;
                }
                .chat-input-area input {
                    flex: 1;
                    padding: 10px;
                    border: 2px solid #e2e8f0;
                    border-radius: 8px;
                    font-size: 14px;
                }
                .chat-input-area input:focus {
                    outline: none;
                    border-color: #667eea;
                }
                .btn-send {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 8px;
                    cursor: pointer;
                    font-weight: 600;
                    transition: all 0.3s ease;
                }
                .btn-send:hover {
                    transform: translateY(-2px);
                }
                .chat-message-item {
                    margin-bottom: 15px;
                    animation: fadeIn 0.3s ease;
                }
                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .chat-message-item.own {
                    text-align: right;
                }
                .message-bubble {
                    display: inline-block;
                    max-width: 70%;
                    padding: 12px 16px;
                    border-radius: 16px;
                    text-align: left;
                    word-wrap: break-word;
                }
                .chat-message-item.own .message-bubble {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border-radius: 16px 16px 4px 16px;
                }
                .chat-message-item.other .message-bubble {
                    background: white;
                    color: #2d3748;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    border-radius: 16px 16px 16px 4px;
                }
                .message-sender {
                    font-size: 12px;
                    font-weight: 600;
                    margin-bottom: 4px;
                    color: #718096;
                }
                .message-time {
                    font-size: 11px;
                    opacity: 0.7;
                    margin-top: 4px;
                }
                .chat-window-header h3 {
                    font-size: 16px;
                    margin: 0;
                }
                
                /* Group Members Modal Styles */
                .group-members-modal {
                    display: none;
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.5);
                    z-index: 10001;
                    justify-content: center;
                    align-items: center;
                }
                .group-members-modal.show {
                    display: flex;
                }
                .group-members-content {
                    background: white;
                    border-radius: 16px;
                    padding: 0;
                    width: 90%;
                    max-width: 500px;
                    max-height: 80vh;
                    overflow: hidden;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    animation: slideUp 0.3s ease;
                }
                @keyframes slideUp {
                    from { opacity: 0; transform: translateY(20px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .group-members-header {
                    padding: 24px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .group-members-header h3 {
                    margin: 0;
                    font-size: 20px;
                    font-weight: 600;
                }
                .group-members-close {
                    font-size: 28px;
                    cursor: pointer;
                    opacity: 0.9;
                    transition: opacity 0.2s;
                    line-height: 1;
                }
                .group-members-close:hover {
                    opacity: 1;
                }
                .group-members-list {
                    padding: 20px;
                    max-height: 50vh;
                    overflow-y: auto;
                }
                .group-members-list::-webkit-scrollbar {
                    width: 8px;
                }
                .group-members-list::-webkit-scrollbar-thumb {
                    background: #cbd5e0;
                    border-radius: 4px;
                }
                .member-item {
                    display: flex;
                    align-items: center;
                    padding: 16px;
                    margin-bottom: 12px;
                    background: #f7fafc;
                    border-radius: 12px;
                    transition: all 0.2s ease;
                    border: 2px solid transparent;
                }
                .member-item:hover {
                    background: #edf2f7;
                    border-color: #e2e8f0;
                    transform: translateX(4px);
                }
                .member-avatar {
                    width: 48px;
                    height: 48px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-weight: 600;
                    font-size: 18px;
                    margin-right: 16px;
                    flex-shrink: 0;
                }
                .member-info {
                    flex: 1;
                }
                .member-name {
                    font-weight: 600;
                    color: #2d3748;
                    font-size: 15px;
                    margin-bottom: 4px;
                }
                .member-email {
                    font-size: 13px;
                    color: #718096;
                }
                .member-role {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 4px 12px;
                    border-radius: 12px;
                    font-size: 12px;
                    font-weight: 600;
                }
                .group-members-footer {
                    padding: 20px 24px;
                    background: #f7fafc;
                    border-top: 1px solid #e2e8f0;
                    display: flex;
                    justify-content: center;
                }
                .btn-leave-group {
                    background: #fc8181;
                    color: white;
                    border: none;
                    padding: 12px 32px;
                    border-radius: 8px;
                    cursor: pointer;
                    font-weight: 600;
                    font-size: 14px;
                    transition: all 0.3s ease;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .btn-leave-group:hover {
                    background: #f56565;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(252, 129, 129, 0.4);
                }
                
                @media (max-width: 768px) {
                    .chat-window {
                        width: calc(100% - 40px);
                        height: calc(100% - 80px);
                        bottom: 20px;
                        right: 20px;
                    }
                    .group-members-content {
                        width: 95%;
                        max-height: 90vh;
                    }
                }
            `;
            document.head.appendChild(style);
        }

        // Create group members modal
        this.createGroupMembersModal();

        return chatWindow;
    }

    // Create group members modal
    createGroupMembersModal() {
        if (document.getElementById('groupMembersModal')) return;

        const modal = document.createElement('div');
        modal.id = 'groupMembersModal';
        modal.className = 'group-members-modal';
        modal.innerHTML = `
            <div class="group-members-content">
                <div class="group-members-header">
                    <h3>👥 สมาชิกกลุ่ม</h3>
                    <span class="group-members-close" onclick="chatManager.closeGroupMembersModal()">×</span>
                </div>
                <div class="group-members-list" id="groupMembersList">
                    <div style="text-align: center; padding: 40px; color: #718096;">
                        Loading members...
                    </div>
                </div>
                <div class="group-members-footer">
                    <button class="btn-leave-group" onclick="chatManager.leaveGroup()">
                        🚪 ออกจากกลุ่ม
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        // Close on outside click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.closeGroupMembersModal();
            }
        });
    }

    // Back to list
    backToList() {
        this.closeChatWindow();
        // Reopen floating chat
        const floatingChat = document.getElementById('floatingChat');
        floatingChat.classList.add('show');
        // Reload the current tab
        const activeTab = document.querySelector('.chat-tab.active');
        const filter = activeTab ? ((activeTab.textContent.includes('My Groups') || activeTab.textContent.includes('กลุ่มของฉัน')) ? 'my' : 'all') : 'my';
        this.loadGroups(filter);
    }

    // Load messages
    async loadMessages() {
        if (!this.currentGroupId) return;

        try {
            const response = await fetch(`../api/chat_api.php?action=get_messages&group_id=${this.currentGroupId}&last_id=${this.lastMessageId}`);
            const data = await response.json();

            if (data.success && data.messages.length > 0) {
                this.displayMessages(data.messages);
                this.lastMessageId = data.messages[data.messages.length - 1].id;

                // Scroll to bottom only if near bottom or first load
                const container = document.getElementById('chatMessagesContainer');
                const isNearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 100;
                if (isNearBottom || this.lastMessageId === data.messages[0].id) {
                    container.scrollTop = container.scrollHeight;
                }
            }
        } catch (error) {
            console.error('Error loading messages:', error);
        }
    }

    // Display messages
    displayMessages(messages) {
        const container = document.getElementById('chatMessagesContainer');

        messages.forEach(msg => {
            const isOwn = msg.user_id == this.userId;
            const messageDiv = document.createElement('div');
            messageDiv.className = `chat-message-item ${isOwn ? 'own' : 'other'}`;

            messageDiv.innerHTML = `
                ${!isOwn ? `<div class="message-sender">${this.escapeHtml(msg.name)}</div>` : ''}
                <div class="message-bubble">
                    ${this.escapeHtml(msg.message)}
                    <div class="message-time">${this.formatTime(msg.created_at)}</div>
                </div>
            `;

            container.appendChild(messageDiv);
        });
    }

    // Send message
    async sendMessage() {
        const input = document.getElementById('chatMessageInput');
        const message = input.value.trim();

        if (!message || !this.currentGroupId) return;

        try {
            const response = await fetch('../api/chat_api.php?action=send_message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    group_id: this.currentGroupId,
                    message: message
                })
            });

            const data = await response.json();

            if (data.success) {
                input.value = '';
                // Update lastMessageId to prevent duplicate when polling
                this.lastMessageId = data.message.id;

                // Display the message immediately
                this.displayMessages([data.message]);

                // Scroll to bottom
                const container = document.getElementById('chatMessagesContainer');
                container.scrollTop = container.scrollHeight;
            } else {
                alert(data.message || 'Failed to send message');
            }
        } catch (error) {
            console.error('Error sending message:', error);
            alert('Failed to send message');
        }
    }

    // Join group
    async joinGroup(groupId) {
        try {
            const response = await fetch('../api/chat_api.php?action=join_group', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ group_id: groupId })
            });

            const data = await response.json();
            return data.success;
        } catch (error) {
            console.error('Error joining group:', error);
            return false;
        }
    }

    // Leave group
    async leaveGroup() {
        if (!this.currentGroupId) return;

        if (!confirm('คุณแน่ใจหรือไม่ว่าต้องการออกจากกลุ่มนี้? คุณสามารถเข้าร่วมกลุ่มใหม่ได้ทุกเมื่อ')) {
            return;
        }

        try {
            const response = await fetch('../api/chat_api.php?action=leave_group', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ group_id: this.currentGroupId })
            });

            const data = await response.json();

            if (data.success) {
                alert('คุณออกจากกลุ่มเรียบร้อยแล้ว');
                this.closeGroupMembersModal();
                this.closeChatWindow();

                // Refresh group list
                const floatingChat = document.getElementById('floatingChat');
                floatingChat.classList.add('show');
                this.loadGroups('my');
            } else {
                alert(data.message || 'Failed to leave group');
            }
        } catch (error) {
            console.error('Error leaving group:', error);
            alert('Failed to leave group');
        }
    }

    // Delete group
    async deleteGroup(groupId) {
        if (!confirm('การลบกลุ่มแชทนี้จะลบข้อความทั้งหมดอย่างถาวร คุณต้องการดำเนินการต่อหรือไม่?')) {
            return;
        }

        try {
            const response = await fetch('../api/chat_api.php?action=delete_group', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ group_id: groupId })
            });

            const data = await response.json();

            if (data.success) {
                alert('ลบกลุ่มแชทเรียบร้อยแล้ว');

                // If the deleted group was currently open, close the chat window
                if (this.currentGroupId === groupId) {
                    this.closeChatWindow();
                } else {
                    const activeTab = document.querySelector('.chat-tab.active');
                    const filter = activeTab ? ((activeTab.textContent.includes('My Groups') || activeTab.textContent.includes('กลุ่มของฉัน')) ? 'my' : 'all') : 'my';
                    this.loadGroups(filter);
                }
            } else {
                alert(data.message || 'ไม่สามารถลบกลุ่มได้');
            }
        } catch (error) {
            console.error('Error deleting group:', error);
            alert('ไม่สามารถลบกลุ่มได้');
        }
    }

    // Start polling for new messages
    startPolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }

        this.isPolling = true;
        this.pollInterval = setInterval(() => {
            if (this.currentGroupId && this.isPolling) {
                this.loadMessages();
                // Also mark as read if window is focused/open
                this.markAsRead(this.currentGroupId);
            }
            // Always poll for global unread count
            this.updateGlobalUnreadCount();
        }, 2000); // Poll every 2 seconds

        // Initial check
        this.updateGlobalUnreadCount();
    }

    // Stop polling
    stopPolling() {
        // Don't fully stop, just stop message loading if chat closed, but keep global badge updates?
        // Actually for simplicity, we'll keep a separate interval for notifications if chat is closed.
        // But here we rely on one interval.
        // Let's change this to only clear if we are destroying the manager, but usually we just want to stop message loading.
        // If chat is closed, we still want badges.

        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }

        // Start a slower poll for just notifications
        this.pollInterval = setInterval(() => {
            this.updateGlobalUnreadCount();
        }, 5000);

        this.isPolling = false;
        this.currentGroupId = null; // Ensure we don't load messages
    }

    async markAsRead(groupId) {
        try {
            await fetch('../api/chat_api.php?action=mark_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ group_id: groupId })
            });
            // Update counts after marking read
            // this.updateGlobalUnreadCount(); // Let the poll handle it to avoid spam
        } catch (error) {
            console.error('Error marking read:', error);
        }
    }

    async updateGlobalUnreadCount() {
        try {
            const response = await fetch(`../api/chat_api.php?action=get_unread_count`);
            const data = await response.json();

            if (data.success) {
                this.updateFloatingButtonBadge(data.count);
            }
        } catch (error) {
            // silent fail
        }
    }

    updateFloatingButtonBadge(count) {
        const btn = document.querySelector('.floating-chat-btn');
        if (!btn) return;

        let badge = btn.querySelector('.chat-badge');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('div');
                badge.className = 'chat-badge';
                btn.appendChild(badge);
            }
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            if (badge) badge.style.display = 'none';
        }
    }

    // Close chat window
    closeChatWindow() {
        const chatWindow = document.getElementById('chatWindow');
        if (chatWindow) {
            chatWindow.style.display = 'none';
        }
        this.stopPolling();
        this.currentGroupId = null;
        this.lastMessageId = 0;
        this.currentView = 'list';

        // Clear messages
        const container = document.getElementById('chatMessagesContainer');
        if (container) {
            container.innerHTML = '';
        }

        // Refresh list to update badges
        const activeTab = document.querySelector('.chat-tab.active');
        const filter = activeTab ? ((activeTab.textContent.includes('My Groups') || activeTab.textContent.includes('กลุ่มของฉัน')) ? 'my' : 'all') : 'my';
        this.loadGroups(filter);
    }

    // Open create group modal
    openCreateGroupModal() {
        document.getElementById('createGroupModal').classList.add('show');
        // Close floating chat when opening modal
        document.getElementById('floatingChat').classList.remove('show');
    }

    // View group info
    async viewGroupInfo() {
        if (!this.currentGroupId) return;

        try {
            const response = await fetch(`../api/chat_api.php?action=get_group_members&group_id=${this.currentGroupId}`);
            const data = await response.json();

            if (data.success) {
                this.displayGroupMembers(data.members);
            }
        } catch (error) {
            console.error('Error loading group info:', error);
        }
    }

    // Display group members
    displayGroupMembers(members) {
        const modal = document.getElementById('groupMembersModal');
        const membersList = document.getElementById('groupMembersList');

        if (members.length === 0) {
            membersList.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #718096;">
                    No members found
                </div>
            `;
        } else {
            let html = '';
            members.forEach(member => {
                const initials = member.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
                const isCreator = member.role === 'creator';

                html += `
                    <div class="member-item">
                        <div class="member-avatar">${initials}</div>
                        <div class="member-info">
                            <div class="member-name">${this.escapeHtml(member.name)}</div>
                            <div class="member-email">${this.escapeHtml(member.email)}</div>
                        </div>
                        ${isCreator ? '<span class="member-role">👑 ผู้สร้างกลุ่ม</span>' : ''}
                    </div>
                `;
            });
            membersList.innerHTML = html;
        }

        modal.classList.add('show');
    }

    // Close group members modal
    closeGroupMembersModal() {
        const modal = document.getElementById('groupMembersModal');
        modal.classList.remove('show');
    }

    // Utility functions
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;

        if (diff < 60000) return 'เพิ่งส่ง';
        if (diff < 3600000) return `${Math.floor(diff / 60000)} นาทีที่แล้ว`;
        if (diff < 86400000) return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
    }
}

// Initialize chat manager
let chatManager;

// Create group
async function createGroup(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    const data = {
        course_id: parseInt(formData.get('course_id')) || chatManager.courseId,
        name: formData.get('group_name'),
        description: formData.get('group_description')
    };

    try {
        const response = await fetch('../api/chat_api.php?action=create_group', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            alert('สร้างกลุ่มแชทเรียบร้อยแล้ว 🎉');
            closeCreateGroupModal();
            // Show floating chat and load groups
            document.getElementById('floatingChat').classList.add('show');
            chatManager.loadGroups('my');
            // Switch to "My Groups" tab
            document.querySelectorAll('.chat-tab').forEach(t => t.classList.remove('active'));
            document.querySelector('.chat-tab:first-child').classList.add('active');
        } else {
            alert(result.message || 'ไม่สามารถสร้างกลุ่มได้');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาด');
    }
}

// Toggle floating chat
function toggleFloatingChat() {
    const floatingChat = document.getElementById('floatingChat');
    const chatWindow = document.getElementById('chatWindow');

    // Close chat window if open
    if (chatWindow && chatWindow.style.display === 'flex') {
        chatManager.closeChatWindow();
    }

    // Toggle floating chat
    const isShowing = floatingChat.classList.toggle('show');

    if (isShowing) {
        // Load groups based on active tab
        const activeTab = document.querySelector('.chat-tab.active');
        const filter = activeTab ? ((activeTab.textContent.includes('My Groups') || activeTab.textContent.includes('กลุ่มของฉัน')) ? 'my' : 'all') : 'my';
        chatManager.loadGroups(filter);
    }
}

// Switch chat tab
function switchChatTab(tab) {
    document.querySelectorAll('.chat-tab').forEach(t => t.classList.remove('active'));
    event.target.classList.add('active');

    chatManager.loadGroups(tab === 'groups' ? 'my' : 'all');
}

// Close create group modal
function closeCreateGroupModal() {
    document.getElementById('createGroupModal').classList.remove('show');
    document.getElementById('createGroupForm').reset();
}

// Close floating chat when clicking outside
document.addEventListener('click', function (event) {
    const floatingChat = document.getElementById('floatingChat');
    const floatingBtn = document.querySelector('.floating-chat-btn');
    const chatWindow = document.getElementById('chatWindow');
    const modals = document.querySelectorAll('.modal');

    // Don't close if clicking inside modal
    let isClickInsideModal = false;
    modals.forEach(modal => {
        if (modal.contains(event.target)) {
            isClickInsideModal = true;
        }
    });

    if (isClickInsideModal) return;

    // Close floating chat if clicking outside
    if (floatingChat &&
        !floatingChat.contains(event.target) &&
        !floatingBtn.contains(event.target) &&
        (!chatWindow || !chatWindow.contains(event.target))) {
        floatingChat.classList.remove('show');
    }
});