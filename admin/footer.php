<!-- (للفوتر المتكرر) -->
            </main>
        </div> 
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const closeBtn = document.querySelector('.close-message-btn');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        this.parentElement.style.display = 'none';
                    });
                }
            });
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const bell = document.getElementById('notifications-bell');
                const dropdown = document.getElementById('notifications-dropdown');
                const badge = document.getElementById('notification-badge');
                const dropdownBody = document.getElementById('dropdown-body-content');

                if (bell) {
                    bell.addEventListener('click', function(e) {
                        e.stopPropagation();
                        
                        // إذا كانت القائمة مفتوحة، أغلقها. وإلا، افتحها واجلب البيانات.
                        if (dropdown.classList.contains('show')) {
                            dropdown.classList.remove('show');
                        } else {
                            dropdown.classList.add('show');
                            dropdownBody.innerHTML = '<p style="text-align:center; padding:1rem;">جار التحميل...</p>';
                            
                            fetch('php/ajax_get_notifications.php')
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success && data.notifications.length > 0) {
                                        dropdownBody.innerHTML = '';
                                        data.notifications.forEach(notif => {
                                            const notifLink = document.createElement('a');
                                            notifLink.href = notif.link;
                                            notifLink.innerHTML = `<p>${notif.message}</p><small>${notif.created_at}</small>`;
                                            dropdownBody.appendChild(notifLink);
                                        });
                                    } else {
                                        dropdownBody.innerHTML = '<p style="text-align:center; padding:1rem;">لا توجد إشعارات جديدة.</p>';
                                    }
                                    // إخفاء الشارة الحمراء بعد فتح القائمة
                                    if(badge) badge.style.display = 'none';
                                })
                                .catch(err => {
                                    dropdownBody.innerHTML = '<p style="text-align:center; padding:1rem;">خطأ في تحميل الإشعارات.</p>';
                                });
                        }
                    });
                }
                
                // إغلاق القائمة عند الضغط في أي مكان آخر
                document.addEventListener('click', function() {
                    if (dropdown && dropdown.classList.contains('show')) {
                        dropdown.classList.remove('show');
                    }
                });
            });
        </script>
</body>
</html>
    </body>
</html>