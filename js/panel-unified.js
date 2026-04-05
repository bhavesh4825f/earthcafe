(function () {
    function fileName(pathname) {
        var parts = pathname.split('/');
        return parts[parts.length - 1].toLowerCase();
    }

    function setActiveLinks(sidebar, currentFile) {
        var links = sidebar.querySelectorAll('a[href]');
        links.forEach(function (a) {
            var href = (a.getAttribute('href') || '').toLowerCase();
            if (href === currentFile) {
                a.classList.add('active');
            }
        });
    }

    function normalizeSidebar(role, currentFile) {
        var sidebar = document.querySelector('.sidebar');
        if (!sidebar) return;
        if (sidebar.querySelectorAll('a[href]').length > 0) {
            setActiveLinks(sidebar, currentFile);
            return;
        }

        var menus = {
            admin: [
                ['dashboard.php', 'fa-home', 'Dashboard'],
                ['add_employee.php', 'fa-user-plus', 'Add Employee'],
                ['manage_services.php', 'fa-cogs', 'Service Builder'],
                ['service_requests.php', 'fa-tasks', 'Applications'],
                ['search.php', 'fa-search', 'Global Search'],
                ['payment_history.php', 'fa-money-bill-wave', 'Payment History'],
                ['manage_contactus.php', 'fa-envelope', 'Contact Queries'],
                ['newsletter.php', 'fa-paper-plane', 'Newsletter'],
                ['manage_users.php', 'fa-users', 'User Management']
            ],
            employee: [
                ['dashboard.php', 'fa-home', 'Dashboard'],
                ['all_applications.php', 'fa-list', 'All Applications'],
                ['manage_profile.php', 'fa-user-edit', 'Profile'],
                ['change_password.php', 'fa-key', 'Change Password'],
                ['search.php', 'fa-search', 'Global Search']
            ]
        };

        var list = menus[role];
        if (!list) return;

        var html = '<h5><i class="fas fa-bars"></i> Menu</h5>';
        list.forEach(function (item) {
            html += '<a href="' + item[0] + '"><i class="fas ' + item[1] + '"></i> ' + item[2] + '</a>';
        });
        sidebar.innerHTML = html;
        setActiveLinks(sidebar, currentFile);
    }

    function injectFooter(role) {
        if (document.querySelector('.panel-footer')) return;
        var footer = document.createElement('footer');
        footer.className = 'panel-footer';
        footer.textContent = '';
        document.body.appendChild(footer);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var path = window.location.pathname.toLowerCase();
        var currentFile = fileName(path);
        var role = path.indexOf('/admin/') !== -1 ? 'admin' : (path.indexOf('/employee/') !== -1 ? 'employee' : '');
        if (!role) return;

        normalizeSidebar(role, currentFile);
        injectFooter(role);
    });
})();
