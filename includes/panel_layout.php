<?php

if (!function_exists('panel_nav_items')) {
    function panel_nav_items(string $role): array
    {
        if ($role === 'admin') {
            return [
                ['dashboard.php', 'fa-home', 'Dashboard'],
                ['add_employee.php', 'fa-user-plus', 'Add Employee'],
                ['manage_services.php', 'fa-cogs', 'Service Builder'],
                ['service_requests.php', 'fa-tasks', 'Applications'],
                ['search.php', 'fa-search', 'Global Search'],
                ['payment_history.php', 'fa-money-bill-wave', 'Payment History'],
                ['manage_contactus.php', 'fa-envelope', 'Contact Queries'],
                ['newsletter.php', 'fa-paper-plane', 'Newsletter'],
                ['manage_users.php', 'fa-users', 'User Management']
            ];
        }

        return [
            ['dashboard.php', 'fa-home', 'Dashboard'],
            ['all_applications.php', 'fa-list', 'All Applications'],
            ['manage_profile.php', 'fa-user-edit', 'Profile'],
            ['change_password.php', 'fa-key', 'Change Password'],
            ['search.php', 'fa-search', 'Global Search']
        ];
    }
}

if (!function_exists('panel_brand_text')) {
    function panel_brand_text(string $role): string
    {
        return $role === 'admin' ? 'Earth Cafe Admin Panel' : 'Earth Cafe Employee Panel';
    }
}

if (!function_exists('panel_icon')) {
    function panel_icon(string $role): string
    {
        return $role === 'admin' ? 'fa-shield-alt' : 'fa-user-tie';
    }
}

if (!function_exists('render_panel_header')) {
    function render_panel_header(string $role, string $userName): void
    {
        ?>
        <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">
                    <i class="fas <?php echo panel_icon($role); ?>"></i> <?php echo htmlspecialchars(panel_brand_text($role)); ?>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#panelNavbar">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="panelNavbar">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <span class="nav-link">Welcome, <strong><?php echo htmlspecialchars($userName); ?></strong></span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        <?php
    }
}

if (!function_exists('render_panel_sidebar')) {
    function render_panel_sidebar(string $role, string $activePage): void
    {
        $items = panel_nav_items($role);
        ?>
        <aside class="sidebar">
            <h5><i class="fas fa-bars"></i> Menu</h5>
            <?php foreach ($items as $item): ?>
                <a href="<?php echo htmlspecialchars($item[0]); ?>" class="<?php echo $activePage === $item[0] ? 'active' : ''; ?>">
                    <i class="fas <?php echo htmlspecialchars($item[1]); ?>"></i> <?php echo htmlspecialchars($item[2]); ?>
                </a>
            <?php endforeach; ?>
        </aside>
        <?php
    }
}

if (!function_exists('render_panel_footer')) {
    function render_panel_footer(string $role): void
    {
        $legalBase = '../';
        ?>
        <footer class="panel-footer">
            <span>&copy; <?php echo date('Y'); ?> Earth Cafe. All Rights Reserved.</span>
            <span class="panel-footer-sep">|</span>
            <a href="<?php echo $legalBase; ?>terms_of_service.php" target="_blank" rel="noopener noreferrer">Terms of Service</a>
            <span class="panel-footer-sep">|</span>
            <a href="<?php echo $legalBase; ?>privacy_policy.php" target="_blank" rel="noopener noreferrer">Privacy Policy</a>
        </footer>
        <?php
    }
}

if (!function_exists('render_panel_styles')) {
    function render_panel_styles(): void
    {
        ?>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            html { scrollbar-gutter: stable; }
            body { background: #edf3ee; font-family: 'Manrope', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
            
            .navbar { 
                background: linear-gradient(135deg, #0f6a5d 0%, #0a4a41 100%); 
                box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
                position: fixed; 
                top: 0; 
                left: 0; 
                right: 0; 
                z-index: 1030;
            }
            
            .navbar-brand { 
                font-weight: 700; 
                font-size: 24px; 
                color: #fff !important; 
            }
            
            .nav-link { 
                color: #ffffff !important; 
                font-weight: 500; 
            }
            
            .nav-link:hover { 
                color: #ffffff !important; 
            }
            
            .sidebar {
                position: fixed; 
                left: 0; 
                top: 60px; 
                width: 250px; 
                height: calc(100vh - 60px);
                background: linear-gradient(180deg, #143730 0%, #0f2924 100%);
                padding: 20px; 
                color: #fff; 
                overflow-y: auto; 
                box-shadow: 2px 0 15px rgba(0,0,0,0.1); 
                z-index: 1020;
            }
            
            .sidebar h5 {
                font-weight: 700; 
                margin-bottom: 20px;
            }
            
            .sidebar a {
                display: flex; 
                align-items: center; 
                gap: 12px; 
                padding: 12px 15px; 
                color: #ffffff;
                text-decoration: none; 
                border-radius: 8px; 
                transition: all 0.3s ease; 
                font-weight: 500; 
                font-size: 14px;
                margin-bottom: 10px;
            }
            
            .sidebar a:hover, 
            .sidebar a.active { 
                background: rgba(255,255,255,0.2); 
                color: #ffffff;
                transform: translateX(5px);
            }
            
            .main-content { 
                margin-left: 250px; 
                margin-top: 60px; 
                padding: 30px; 
            }
            
            .dashboard-header {
                background: #fff; 
                padding: 20px 30px; 
                border-radius: 10px; 
                margin-bottom: 25px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
                display: flex; 
                justify-content: space-between; 
                align-items: center;
            }
            
            .dashboard-header h1 { 
                color: #333; 
                margin: 0; 
                font-size: 28px; 
                font-weight: 700; 
            }
            
            .welcome-text { 
                color: #666; 
                font-size: 14px; 
            }
            
            .panel-footer {
                background: #f8f9fa;
                padding: 15px 30px;
                text-align: center;
                color: #666;
                font-size: 12px;
                border-top: 1px solid #e9ecef;
                margin-left: 250px;
                margin-top: 30px;
            }

            .panel-footer a {
                color: #0f6a5d;
                font-weight: 600;
                text-decoration: none;
            }

            .panel-footer a:hover {
                color: #09483f;
                text-decoration: underline;
            }

            .panel-footer-sep {
                margin: 0 8px;
                color: #9ab0aa;
            }
            
            @media (max-width: 768px) {
                .sidebar { 
                    width: 200px; 
                }
                .main-content { 
                    margin-left: 200px; 
                    padding: 20px; 
                }
                .dashboard-header { 
                    flex-direction: column; 
                    align-items: flex-start; 
                    gap: 12px; 
                }
                .panel-footer {
                    margin-left: 200px;
                }
            }
            
            @media (max-width: 576px) {
                .sidebar { 
                    width: 150px; 
                    padding: 15px; 
                }
                .main-content { 
                    margin-left: 150px; 
                    padding: 15px; 
                }
                .dashboard-header h1 { 
                    font-size: 21px; 
                }
                .panel-footer {
                    margin-left: 150px;
                }
            }
        </style>
        <?php
    }
}
