<?php
require_once 'backend/session.php';
require_once 'backend/workflow.php';
requireAdminSessionOrRedirect('admin-login.html');
if (!isAdminRecoveryConfigured()) {
    header("Location: admin-recovery-setup.html");
    exit;
}
$csrfToken = getCsrfToken();
$statusLabels = jthStatusLabels();
$workflowTransitions = jthWorkflowTransitions();
$terminalStatuses = jthTerminalStatuses();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- FullCalendar -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { 
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    colors: {
                        bg: "#FFFFFF",
                        surface: "#FFFFFF",
                        primary: "#18181B", // Zinc-900
                        secondary: "#71717A", // Zinc-500
                        border: "#E4E4E7" // Zinc-200
                    }
                }
            }
        }
    </script>
   <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        body { background-color: #FFFFFF; color: #18181B; }
        .hidden-section { display: none !important; }
        .fade-in { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        .bg-surface.border-border {
            box-shadow: 0 4px 14px rgba(24, 24, 27, 0.05);
        }
        
        .glass-nav { background: rgba(255, 255, 255, 0.95); border-bottom: 1px solid #E4E4E7; }
        .nav-pill.active { background-color: #18181B; color: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .nav-pill:not(.active):hover { background-color: #E4E4E7; color: #18181B; }
        .table-row-anim:hover { background-color: #FAFAFA; }
            font-family: 'Inter', sans-serif !important;
        }
        .fc .fc-icon,
        .fc .fc-icon::before {
            font-family: fcicons !important;
        }

        .calendar-wrapper { flex: 1; display: flex; flex-direction: column; overflow: hidden; position: relative; }
        .fc { flex-grow: 1; height: 100% !important; width: 100%; }
        .fc-view-harness { flex-grow: 1; height: 100% !important; }
        
        .fc-header-toolbar { margin-bottom: 1.25rem !important; }
        .fc-toolbar-title { font-size: 1.25rem !important; font-weight: 700; font-family: 'Inter', sans-serif; color: #18181B; }
        .fc .fc-toolbar-chunk { display: flex; align-items: center; gap: 0.6rem; }
        .fc .fc-button-group { display: inline-flex; gap: 0.45rem; }
        .fc .fc-button-group > .fc-button { margin: 0 !important; }
        @keyframes calendarViewFadeIn {
            from { opacity: 0.35; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }
            animation: calendarViewFadeIn .24s ease;
        }

        .fc-button-primary {
            background-color: #FFFFFF !important;
            border: 1px solid #D4D4D8 !important;
            color: #18181B !important;
            font-size: 0.75rem !important;
            font-weight: 600 !important;
            text-transform: capitalize !important;
            padding: 7px 14px !important;
            border-radius: 12px !important;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
            outline: none !important;
            transition: all 0.15s ease !important;
        }
        .fc-button-primary:hover {
            background-color: #F4F4F5 !important;
            border-color: #A1A1AA !important;
            color: #18181B !important;
            box-shadow: 0 3px 10px rgba(24,24,27,0.08) !important;
            transform: translateY(-1px);
        }
        .fc-button-primary:focus { box-shadow: 0 0 0 2px rgba(24, 24, 27, 0.1) !important; }
        .fc-button-active { background-color: #18181B !important; color: #FFFFFF !important; border-color: #18181B !important; }
        .fc .fc-prev-button,
        .fc .fc-next-button,
        .fc .fc-today-button {
            background-color: #FFFFFF !important;
            border-color: #D4D4D8 !important;
            color: #18181B !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
            transform: none !important;
        }
        .fc .fc-prev-button:hover,
        .fc .fc-next-button:hover,
        .fc .fc-today-button:hover {
            background-color: #F4F4F5 !important;
            border-color: #A1A1AA !important;
            color: #18181B !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06) !important;
            transform: none !important;
        }
        
        .fc-event { border: none !important; border-radius: 8px; padding: 3px 7px; font-size: 0.7rem; font-weight: 600; margin-bottom: 2px !important; transition: transform .18s ease, opacity .18s ease; }
        .fc-daygrid-day { transition: background-color 0.15s ease; }
        .fc-daygrid-day:hover { background-color: #F9FAFB !important; }
        .fc-day-today { background-color: #EEF6FF !important; }
        .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
            background: #DBEAFE;
            color: #1E3A8A;
            border-radius: 9999px;
            padding: 2px 7px;
            font-weight: 700;
            margin-top: 2px;
            margin-right: 2px;
        }
        .fc-col-header-cell { padding: 10px 0; background: #FAFAFA; border-bottom: 1px solid #E4E4E7; color: #71717A; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; }

        .modal-overlay { 
            position: fixed; inset: 0; z-index: 100; background-color: rgba(0, 0, 0, 0.5);
            display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: opacity 0.2s ease;
        }
        .modal-overlay.open { opacity: 1; pointer-events: auto; }
        .modal-content {
            background: white; width: 100%; max-width: 400px;
            border: 1px solid #E4E4E7;
            border-radius: 12px;
            box-shadow: 0 24px 50px -12px rgba(0, 0, 0, 0.25);
            transform: scale(0.95); opacity: 0; transition: transform 0.2s ease, opacity 0.2s ease;
        }
        .modal-overlay.open .modal-content { transform: scale(1); opacity: 1; }
        @media (max-width: 767px) {
            .modal-overlay {
                padding: 14px;
            }
            .modal-content {
                max-height: calc(100vh - 28px);
                overflow-y: auto;
            }
        }
        div:where(.swal2-container) div:where(.swal2-popup) { border-radius: 12px; border: 1px solid #E4E4E7; font-family: 'Inter', sans-serif; }
        .mobile-nav-fab {
            position: fixed;
            left: 50%;
            bottom: max(34px, calc(env(safe-area-inset-bottom, 0px) + 20px));
            transform: translateX(-50%);
            z-index: 55;
            display: none;
            width: auto;
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid #E4E4E7;
            border-radius: 999px;
            box-shadow: 0 14px 34px rgba(0,0,0,0.16);
            backdrop-filter: blur(10px);
            padding: 4px;
            gap: 2px;
            justify-content: center;
            animation: mobileNavEnter .36s cubic-bezier(.22,1,.36,1);
            overflow-x: auto;
            overflow-y: hidden;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            scroll-snap-type: x proximity;
        }
        .mobile-nav-fab::-webkit-scrollbar { display: none; }
        .mobile-nav-btn {
            flex: 0 0 auto;
            width: 40px;
            height: 34px;
            border: 0;
            background: transparent;
            color: #71717A;
            padding: 0;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color .22s ease, color .22s ease, transform .2s ease;
            scroll-snap-align: center;
        }
        .mobile-nav-btn.active {
            background: #18181B;
            color: #FFFFFF;
        }
        .mobile-nav-btn:active {
            transform: scale(0.92);
        }
        .mobile-nav-btn.active svg {
            animation: navIconPop .24s ease;
        }
        .grid-checkbox {
            width: 16px;
            height: 16px;
            border-radius: 6px;
            border: 1px solid #D4D4D8;
            accent-color: #18181B;
            background: #fff;
            cursor: pointer;
        }
        .mobile-add-variant-fab {
            position: fixed;
            right: 16px;
            bottom: max(96px, calc(env(safe-area-inset-bottom, 0px) + 82px));
            z-index: 56;
            width: 48px;
            height: 48px;
            border-radius: 999px;
            background: #18181B;
            color: #FFFFFF;
            border: 1px solid #27272A;
            box-shadow: 0 14px 28px rgba(0, 0, 0, 0.22);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform .2s ease, background-color .2s ease, box-shadow .2s ease;
        }
        .mobile-add-variant-fab:hover {
            background: #27272A;
            transform: translateY(-1px);
        }
        .mobile-add-variant-fab:active {
            transform: scale(0.94);
        }
        @keyframes mobileNavEnter {
            from { opacity: 0; transform: translateX(-50%) translateY(10px); }
            to { opacity: 1; transform: translateX(-50%) translateY(0); }
        }
        @keyframes navIconPop {
            from { transform: scale(0.86); }
            to { transform: scale(1); }
        }
        @media (max-width: 767px) {
            .mobile-nav-fab { display: flex; }
                height: auto;
                min-height: calc(100vh - 64px);
            }
            .calendar-wrapper {
                min-height: 560px;
            }
            .fc .fc-header-toolbar {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                row-gap: 8px;
                column-gap: 8px;
                margin-bottom: 0.9rem !important;
            }
            .fc .fc-toolbar-chunk:nth-child(2) {
                order: 3;
                flex: 1 1 100%;
                display: flex;
                justify-content: center;
            }
            .fc .fc-toolbar-title {
                font-size: 1.9rem !important;
                line-height: 1.05;
                text-align: center;
                margin: 0 !important;
            }
            .fc .fc-toolbar-chunk:nth-child(1),
            .fc .fc-toolbar-chunk:nth-child(3) {
                order: 1;
            }
            .fc .fc-button-primary {
                padding: 6px 10px !important;
                border-radius: 10px !important;
                font-size: 0.7rem !important;
            }
            .fc .fc-toolbar-chunk {
                gap: 0.4rem !important;
            }
        }
    </style>
</head>
<body class="flex flex-col h-screen overflow-hidden text-sm bg-bg">

    <!-- HEADER -->
    <header class="h-16 glass-nav z-40 flex items-center justify-between px-6 shrink-0 relative">
        <div class="flex items-center gap-2 w-48">
            <div class="w-7 h-7 bg-primary rounded-md flex items-center justify-center shadow-sm" title="Admin">
                <svg xmlns="http://www.w3.org/2000/svg" class="text-white h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21a8 8 0 0 0-16 0"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            </div>
            <span class="font-bold text-lg tracking-tight text-primary">Admin</span>
        </div>

        <nav class="hidden md:flex items-center p-1 bg-white border border-border rounded-full shadow-sm" id="main-nav">
            <button onclick="switchView('overview')" data-view="overview" class="nav-pill active px-5 py-1.5 rounded-full text-xs font-medium text-secondary">Overview</button>
            <button onclick="switchView('calendar')" data-view="calendar" class="nav-pill px-5 py-1.5 rounded-full text-xs font-medium text-secondary">Calendar</button>
            <button onclick="switchView('kanban')" data-view="kanban" class="nav-pill px-5 py-1.5 rounded-full text-xs font-medium text-secondary">Workflow</button>
            <button onclick="switchView('database')" data-view="database" class="nav-pill px-5 py-1.5 rounded-full text-xs font-medium text-secondary">Customers</button>
            <button onclick="switchView('products')" data-view="products" class="nav-pill px-5 py-1.5 rounded-full text-xs font-medium text-secondary">Products</button>
        </nav>

        <div class="flex items-center gap-4 w-48 justify-end">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse" id="live-indicator"></span>
                <span class="font-mono text-[10px] text-zinc-400 uppercase" id="last-updated">Syncing...</span>
            </div>
            <button onclick="openSecurityModal()" class="text-zinc-400 hover:text-zinc-900 transition" title="Security Alerts">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M12 3l7 4v5c0 5-3 8-7 9-4-1-7-4-7-9V7l7-4z"></path>
                    <path d="M9.5 12.5 11.5 14.5 15 11"></path>
                </svg>
            </button>
            <button onclick="openRecoveryRegenerateModal()" class="text-zinc-400 hover:text-zinc-900 transition" title="Regenerate Recovery Code">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="8" cy="12" r="3"></circle>
                    <path d="M11 12h10"></path>
                    <path d="M18 12v3"></path>
                    <path d="M21 12v2"></path>
                </svg>
            </button>
            <button onclick="logout()" class="text-zinc-400 hover:text-red-600 transition" title="Sign Out">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </button>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="flex-1 overflow-hidden relative max-w-[1600px] w-full mx-auto">
        
        <!-- VIEW 1: OVERVIEW -->
        <div id="view-overview" class="h-full overflow-y-auto xl:overflow-hidden p-6 lg:p-8 pb-28 md:pb-8 space-y-6 xl:space-y-0 xl:flex xl:flex-col xl:gap-6 fade-in">
            
            <!-- Stats Row -->
            <div class="grid grid-cols-3 gap-2 md:gap-6">
                <!-- Pipeline Value Card -->
                <div class="bg-surface p-3 md:p-6 rounded-xl md:rounded-2xl border border-border shadow-sm">
                    <div class="flex justify-between mb-1 md:mb-2">
                        <span class="text-[9px] md:text-xs font-bold uppercase tracking-wider text-zinc-400">Projected Value</span>
                        <span class="hidden md:inline text-[10px] font-mono text-zinc-400">PIPELINE + ACTIVE</span>
                    </div>
                    <div class="text-base sm:text-lg md:text-3xl font-bold tracking-tight mb-1 md:mb-2 text-primary leading-tight whitespace-nowrap overflow-hidden text-ellipsis" id="rev-total">₱0.00</div>
                    <div class="hidden md:flex gap-1 h-1.5 w-full bg-zinc-100 rounded-full overflow-hidden">
                        <div class="bg-primary h-full" id="rev-bar-conf" style="width: 0%"></div>
                    </div>
                    <div class="hidden md:flex justify-between mt-3 text-[10px] uppercase font-mono text-zinc-500">
                        <span title="Confirmed + Fabrication + Installation">Secured: <b class="text-primary" id="txt-secured">₱0.00</b></span>
                        <span title="Pending + Site Visit">Pipeline: <b class="text-zinc-400" id="txt-pipeline">₱0.00</b></span>
                    </div>
                </div>
                
                <!-- Active Health Card -->
                <div class="bg-surface p-3 md:p-6 rounded-xl md:rounded-2xl border border-border shadow-sm">
                     <div class="mb-1 md:mb-4">
                        <span class="text-[9px] md:text-xs font-bold uppercase tracking-wider text-zinc-400">Production Load</span>
                    </div>
                    <div class="flex items-baseline gap-1 md:gap-2 mb-0.5 md:mb-1">
                        <span class="text-lg md:text-3xl font-bold text-primary leading-tight" id="active-jobs">0</span>
                        <span class="text-[10px] md:text-xs text-secondary">active</span>
                    </div>
                    <p class="hidden md:block text-[11px] text-zinc-400">Strictly Fabrication & Installation status.</p>
                </div>
                
                <!-- Attention Card -->
                <div class="bg-primary text-white p-3 md:p-6 rounded-xl md:rounded-2xl border border-primary shadow-lg shadow-zinc-200">
                    <div class="flex justify-between mb-1 md:mb-4 opacity-70"><span class="text-[9px] md:text-xs font-bold uppercase tracking-wider">Action Required</span></div>
                    <div class="text-lg md:text-3xl font-bold mb-0.5 md:mb-2 leading-tight" id="pend-count">0</div>
                    <p class="hidden md:block text-[11px] text-zinc-400">New inquiries waiting for review.</p>
                </div>
            </div>

            <!-- Mobile Popular Products -->
            <div class="md:hidden bg-surface p-4 rounded-2xl border border-border shadow-sm">
                <h3 class="text-[11px] font-bold uppercase text-zinc-400 mb-3 tracking-wider">Popular Products</h3>
                <div id="demand-list-mobile" class="space-y-3"></div>
            </div>
            
            <!-- Main Grid -->
            <div class="grid grid-cols-1 xl:grid-cols-4 gap-3 md:gap-6 xl:flex-1 xl:min-h-0">
                <!-- Table -->
                <div class="xl:col-span-3 bg-surface rounded-2xl border border-border shadow-sm flex flex-col h-[600px] xl:h-full xl:min-h-0">
                    <!-- Table Controls -->
                    <div class="p-4 border-b border-border flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3">
                        <div class="flex gap-1 p-1 bg-zinc-100 rounded-lg w-full sm:w-auto overflow-x-auto">
                             <button class="px-3 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide bg-white shadow-sm text-primary transition filter-btn" data-filter="All">Active</button>
                             <button class="px-3 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide text-zinc-500 hover:text-primary transition filter-btn" data-filter="Pending">Pending</button>
                             <button class="px-3 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide text-zinc-500 hover:text-primary transition filter-btn" data-filter="Confirmed">Confirmed</button>
                             <button class="px-3 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide text-zinc-500 hover:text-red-600 transition filter-btn" data-filter="Archived">Archived</button>
                        </div>
                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            <select id="sort-order" class="px-2 py-1.5 text-xs bg-zinc-50 border border-zinc-200 rounded-lg focus:outline-none focus:border-primary transition">
                                <option value="newest">Most Recent</option>
                                <option value="oldest">Oldest First</option>
                            </select>
                            <div class="relative w-full sm:w-48">
                                <svg class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="11" cy="11" r="7"></circle>
                                    <path d="m20 20-3.5-3.5"></path>
                                </svg>
                                <input type="text" id="search-box" placeholder="Search customer, ref ID..." 
                                    class="pl-8 pr-3 py-1.5 text-xs bg-zinc-50 border border-zinc-200 rounded-lg w-full focus:outline-none focus:border-primary transition">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Table Header + Body (single scroll container for mobile) -->
                    <div class="flex-1 overflow-auto no-scrollbar">
                        <div class="min-w-[760px]">
                            <div class="grid grid-cols-12 px-6 py-3 bg-zinc-50 border-b border-border text-[10px] font-bold text-zinc-400 uppercase tracking-wider sticky top-0 z-10">
                                <div class="col-span-2">Ref ID</div><div class="col-span-3">Customer</div><div class="col-span-2">Product</div><div class="col-span-2 text-right">Value</div><div class="col-span-2 text-center">Status</div><div class="col-span-1 text-center">View</div>
                            </div>
                            <div id="data-list" class="divide-y divide-zinc-100"></div>
                        </div>
                    </div>
                    <div id="pagination-controls" class="px-6 py-3 border-t border-border bg-white flex items-center justify-between text-xs"></div>
                    
                    <!-- Footer -->
                    <div class="p-3 border-t border-border bg-zinc-50/50 flex justify-between items-center text-[10px] text-zinc-400 px-6">
                        <span id="record-count">Loading...</span>
                        <button onclick="downloadCSV()" class="hover:text-primary">Download CSV</button>
                    </div>
                </div>
                
                <!-- Side Widgets -->
                <div class="space-y-3 md:space-y-6 xl:space-y-0 xl:h-full xl:min-h-0 xl:flex xl:flex-col xl:gap-6">
                    <div class="hidden md:block bg-surface p-5 rounded-2xl border border-border shadow-sm xl:shrink-0">
                        <h3 class="text-xs font-bold uppercase text-zinc-400 mb-4 tracking-wider">Popular Products</h3>
                        <div id="demand-list" class="space-y-4"></div>
                    </div>
                    <div class="bg-surface rounded-2xl border border-border shadow-sm h-[500px] max-h-[62vh] xl:h-auto xl:max-h-none xl:flex-1 xl:min-h-0 flex flex-col overflow-hidden">
                        <div class="p-5 pb-3 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/85 border-b border-zinc-100 sticky top-0 z-10">
                            <div class="mb-3">
                                <h3 class="text-sm font-bold uppercase text-zinc-400 tracking-wider">Audit Log</h3>
                            </div>
                            <div class="grid grid-cols-2 gap-2 mb-2">
                                <select id="audit-filter-action" class="h-8 px-2.5 text-xs border border-zinc-200 rounded-lg bg-white w-full min-w-0">
                                    <option value="all">All Actions</option>
                                    <option value="status_change">Status Change</option>
                                    <option value="auto_void">Auto Void</option>
                                    <option value="created">Created</option>
                                    <option value="customer_cancel">Customer Cancel</option>
                                </select>
                                <select id="audit-filter-actor" class="h-8 px-2.5 text-xs border border-zinc-200 rounded-lg bg-white w-full min-w-0">
                                    <option value="all">All Actors</option>
                                </select>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="space-y-1 col-span-1">
                                    <label for="audit-filter-from" class="block text-[10px] font-semibold uppercase tracking-wider text-zinc-400">From</label>
                                    <input id="audit-filter-from" type="date" class="w-full px-2 py-1.5 text-xs border border-zinc-200 rounded-lg bg-white">
                                </div>
                                <div class="space-y-1 col-span-1">
                                    <label for="audit-filter-to" class="block text-[10px] font-semibold uppercase tracking-wider text-zinc-400">To</label>
                                    <input id="audit-filter-to" type="date" class="w-full px-2 py-1.5 text-xs border border-zinc-200 rounded-lg bg-white">
                                </div>
                                <div class="col-span-1 flex items-end">
                                    <button
                                        type="button"
                                        id="audit-filter-clear-dates"
                                        class="w-full px-3 py-1.5 text-xs border border-zinc-200 rounded-lg bg-white text-zinc-600 hover:text-primary hover:border-primary transition"
                                    >
                                        Clear
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto p-5 pt-4">
                            <div class="border-l border-zinc-200 ml-1.5 pl-4 space-y-5" id="audit-log-widget"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- VIEW 2: CALENDAR -->
        <div id="view-calendar" class="h-full flex flex-col p-6 pb-28 md:pb-6 hidden-section fade-in">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-bold">Production Schedule</h2>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        onclick="openMobileStatusLegend()"
                        class="md:hidden px-3 py-2 text-xs border border-zinc-200 rounded-lg bg-white text-zinc-600 hover:text-primary hover:border-primary transition"
                    >
                        Note
                    </button>
                    <button type="button" onclick="openBlockModal(event)" class="bg-primary text-white px-5 py-2.5 rounded-xl text-xs font-bold shadow-md shadow-zinc-200 hover:bg-zinc-800 transition">
                        + Block Date
                    </button>
                </div>
            </div>
            <div id="calendar-state-msg" class="mb-3 text-xs text-zinc-500 hidden"></div>
            <div class="calendar-wrapper bg-surface rounded-2xl border border-border p-4 shadow-sm">
                <div id='calendar'></div>
            </div>
        </div>

        <!-- VIEW 3: KANBAN -->
        <div id="view-kanban" class="h-full overflow-auto no-scrollbar p-6 pb-28 md:pb-6 hidden-section fade-in">
            <div class="flex gap-6 h-full min-w-[1000px] pb-4">
                <!-- Columns -->
                <div class="flex-1 flex flex-col bg-zinc-100/50 rounded-xl border border-zinc-200 p-3">
                    <div class="mb-3 flex justify-between px-2"><span class="font-bold text-xs uppercase text-zinc-500">Pipeline</span><span class="bg-white px-2 rounded-full text-[10px] border shadow-sm" id="count-pending">0</span></div>
                    <div class="flex-1 space-y-3 overflow-y-auto pr-1" id="col-pending"></div>
                </div>
                <div class="flex-1 flex flex-col bg-blue-50/50 rounded-xl border border-blue-100 p-3">
                    <div class="mb-3 flex justify-between px-2"><span class="font-bold text-xs uppercase text-blue-600">Site Visit / Quote</span><span class="bg-white px-2 rounded-full text-[10px] border shadow-sm" id="count-confirmed">0</span></div>
                    <div class="flex-1 space-y-3 overflow-y-auto pr-1" id="col-confirmed"></div>
                </div>
                <div class="flex-1 flex flex-col bg-orange-50/50 rounded-xl border border-orange-100 p-3">
                    <div class="mb-3 flex justify-between px-2"><span class="font-bold text-xs uppercase text-orange-600">Fabrication</span><span class="bg-white px-2 rounded-full text-[10px] border shadow-sm" id="count-fabrication">0</span></div>
                    <div class="flex-1 space-y-3 overflow-y-auto pr-1" id="col-fabrication"></div>
                </div>
                <div class="flex-1 flex flex-col bg-purple-50/50 rounded-xl border border-purple-100 p-3">
                    <div class="mb-3 flex justify-between px-2"><span class="font-bold text-xs uppercase text-purple-600">Installation</span><span class="bg-white px-2 rounded-full text-[10px] border shadow-sm" id="count-installation">0</span></div>
                    <div class="flex-1 space-y-3 overflow-y-auto pr-1" id="col-installation"></div>
                </div>
            </div>
        </div>

        <!-- VIEW 4: DATABASE -->
        <div id="view-database" class="h-full overflow-y-auto p-6 pb-28 md:pb-6 hidden-section fade-in">
            <h2 class="text-lg font-bold mb-6">Customers</h2>
            <div class="bg-surface rounded-2xl border border-border shadow-sm overflow-hidden">
                <div class="p-4 border-b border-border space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <div class="relative w-full max-w-[280px]">
                            <svg class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="11" cy="11" r="7"></circle>
                                <path d="m20 20-3.5-3.5"></path>
                            </svg>
                            <input
                                type="text"
                                id="customer-search"
                                placeholder="Search customer name..."
                                class="pl-8 pr-3 py-2 text-xs bg-zinc-50 border border-zinc-200 rounded-lg w-full focus:outline-none focus:border-primary transition"
                            >
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <span id="retention-status-label" class="text-xs text-zinc-500 whitespace-nowrap px-2.5 py-1 rounded-md border border-zinc-200 bg-zinc-50">Retention: checking...</span>
                            <span id="customer-count" class="text-[10px] text-zinc-600 whitespace-nowrap px-2.5 py-1 rounded-md border border-zinc-200 bg-white">0 customers</span>
                        </div>
                    </div>
                </div>
                <div class="overflow-auto no-scrollbar">
                    <div class="min-w-[760px]">
                        <div class="grid grid-cols-12 px-6 py-3 bg-zinc-50 border-b border-border text-[10px] font-bold text-zinc-400 uppercase tracking-wider">
                            <div class="col-span-6">Customer</div>
                            <div class="col-span-2">Last Activity</div>
                            <div class="col-span-1 text-right">Bookings</div>
                            <div class="col-span-3 text-right">Total Value</div>
                        </div>
                        <div id="customer-list" class="divide-y divide-zinc-100"></div>
                    </div>
                </div>
                <div id="customer-pagination" class="px-6 py-3 border-t border-border bg-white flex items-center justify-between text-xs"></div>
            </div>
        </div>

        <!-- VIEW 5: PRODUCTS -->
        <div id="view-products" class="h-full overflow-y-auto p-6 pb-28 md:pb-6 hidden-section fade-in">
            <h2 class="text-lg font-bold mb-6">Product Pricing</h2>
            <div class="bg-surface rounded-2xl border border-border shadow-sm overflow-hidden">
                <div class="p-4 border-b border-border flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3">
                    <div>
                        <h3 class="text-sm md:text-base font-bold text-primary">Product Pricing</h3>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 w-full lg:max-w-2xl lg:justify-end">
                        <button
                            type="button"
                            onclick="openProductCreateModal()"
                            aria-label="Add Variant"
                            title="Add Variant"
                            class="hidden sm:inline-flex h-8 sm:w-auto px-2.5 items-center justify-center gap-1 text-[11px] font-semibold rounded-lg bg-zinc-900 text-white hover:bg-zinc-800 transition whitespace-nowrap"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 5v14M5 12h14"></path>
                            </svg>
                            <span class="hidden sm:inline">Add Variant</span>
                        </button>
                        <select id="product-type-filter" class="h-8 px-2.5 text-xs bg-zinc-50 border border-zinc-200 rounded-lg focus:outline-none focus:border-primary transition w-auto min-w-[96px]">
                            <option value="all">All Types</option>
                            <option value="windows">Windows</option>
                            <option value="doors">Doors</option>
                            <option value="sliding">Sliding</option>
                            <option value="partitions">Partitions</option>
                            <option value="railings">Railings</option>
                            <option value="accessories">Accessories</option>
                            <option value="others">Others</option>
                        </select>
                        <div class="relative w-full sm:w-[240px]">
                            <svg class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="11" cy="11" r="7"></circle>
                                <path d="m20 20-3.5-3.5"></path>
                            </svg>
                            <input
                                type="text"
                                id="product-search"
                                placeholder="Search product or variant..."
                                class="pl-8 pr-3 py-2 text-xs bg-zinc-50 border border-zinc-200 rounded-lg w-full focus:outline-none focus:border-primary transition"
                            >
                        </div>
                    </div>
                </div>
                <div class="overflow-auto no-scrollbar">
                    <div class="md:min-w-[980px]">
                        <div class="hidden md:grid grid-cols-12 px-6 py-3 bg-zinc-50 border-b border-border text-[10px] font-bold text-zinc-400 uppercase tracking-wider">
                            <div class="col-span-1 text-center">Select</div>
                            <div class="col-span-2">Product</div>
                            <div class="col-span-4">Variant</div>
                            <div class="col-span-2">Type</div>
                            <div class="col-span-2 text-right">Base Price</div>
                            <div class="col-span-1 text-center">Active</div>
                        </div>
                        <div id="product-list" class="divide-y divide-zinc-100"></div>
                    </div>
                </div>
                <div id="product-pagination" class="px-4 md:px-6 py-3 border-t border-border bg-white flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs"></div>
            </div>
        </div>
    </main>

    <button
        id="mobile-add-variant-fab"
        type="button"
        onclick="openProductCreateModal()"
        class="mobile-add-variant-fab md:hidden hidden"
        aria-label="Add Variant"
        title="Add Variant"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 5v14M5 12h14"></path>
        </svg>
    </button>
    <button
        id="mobile-selected-actions-fab"
        type="button"
        onclick="openMobileSelectedActions()"
        class="mobile-add-variant-fab md:hidden hidden"
        style="bottom:max(154px, calc(env(safe-area-inset-bottom, 0px) + 140px));"
        aria-label="Selected Actions"
        title="Selected Actions"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M4 6h16"></path>
            <path d="M7 12h10"></path>
            <path d="M10 18h4"></path>
        </svg>
    </button>

    <nav id="main-nav-mobile" class="mobile-nav-fab md:hidden" aria-label="Mobile Admin Navigation">
        <button onclick="switchView('overview')" data-view="overview" class="mobile-nav-btn active" title="Overview" aria-label="Overview">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12h18"/><path d="M3 6h18"/><path d="M3 18h18"/></svg>
            <span class="sr-only">Overview</span>
        </button>
        <button onclick="switchView('calendar')" data-view="calendar" class="mobile-nav-btn" title="Calendar" aria-label="Calendar">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
            <span class="sr-only">Calendar</span>
        </button>
        <button onclick="switchView('kanban')" data-view="kanban" class="mobile-nav-btn" title="Workflow" aria-label="Workflow">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="6" height="16" rx="1"/><rect x="10.5" y="4" width="10.5" height="7" rx="1"/><rect x="10.5" y="13" width="10.5" height="7" rx="1"/></svg>
            <span class="sr-only">Workflow</span>
        </button>
        <button onclick="switchView('database')" data-view="database" class="mobile-nav-btn" title="Customers" aria-label="Customers">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
            <span class="sr-only">Customers</span>
        </button>
        <button onclick="switchView('products')" data-view="products" class="mobile-nav-btn" title="Products" aria-label="Products">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
            <span class="sr-only">Products</span>
        </button>
    </nav>

    <!-- MODAL 1: UPDATE STATUS -->
    <div id="action-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="p-6">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-primary" id="m-cust">Customer</h3>
                        <span class="text-[10px] font-mono bg-zinc-100 px-2 py-0.5 rounded text-zinc-500" id="m-id">REF</span>
                    </div>
                    <button onclick="closeModal('action-modal')" class="text-zinc-400 hover:text-primary">&times;</button>
                </div>
                <div class="space-y-3 text-sm mb-6 bg-zinc-50 p-4 rounded-lg border border-zinc-100">
                    <div class="flex justify-between"><span class="text-secondary">Product</span><span class="font-medium text-right" id="m-prod">--</span></div>
                    <div class="flex justify-between"><span class="text-secondary">Value</span><span class="font-bold text-primary" id="m-price">--</span></div>
                    <div class="flex justify-between"><span class="text-secondary">Preferred Site Visit Date</span><span class="font-medium" id="m-date">--</span></div>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block mb-2">Update Lifecycle Stage</label>
                    <div class="relative">
                        <select id="m-status" class="w-full h-10 border border-zinc-200 rounded-lg px-3 bg-white outline-none focus:border-primary transition text-xs appearance-none">
                            <option value="Pending">1. Pending Review</option>
                            <option value="Site Visit">2. Site Visit</option>
                            <option value="Confirmed">3. Confirmed (Job)</option>
                            <option value="Fabrication">4. Fabrication</option>
                            <option value="Installation">5. Installation</option>
                            <option value="Completed">6. Completed</option>
                            <option disabled>──────────</option>
                            <option value="Cancelled">Cancelled</option>
                            <option value="Void">Void (Expired)</option>
                        </select>
                        <svg class="absolute right-3 top-3 text-zinc-400 w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block mb-2">Preferred Site Visit Date</label>
                    <input id="m-install-date" type="date" class="w-full h-10 border border-zinc-200 rounded-lg px-3 bg-white outline-none focus:border-primary transition text-xs">
                    <p class="text-[10px] text-zinc-400 mt-1">Optional: update schedule date. Past dates are not allowed.</p>
                </div>
                <div class="mt-6">
                    <button onclick="saveChanges()" class="w-full h-10 text-xs font-bold rounded-lg bg-primary text-white hover:bg-zinc-800 transition shadow-lg shadow-zinc-200">Save Update</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL 2: BLOCK DATE -->
    <div id="block-modal" class="modal-overlay">
        <div id="block-modal-content" class="modal-content" style="max-width: 470px;">
            <div class="p-6">
                <div class="flex justify-between items-start mb-5">
                    <div>
                        <h3 class="text-lg font-semibold text-zinc-900">Block Date</h3>
                        <p class="text-sm text-zinc-500 mt-1">Prevent customers from booking on this date.</p>
                    </div>
                    <button type="button" onclick="closeModal('block-modal', true)" class="h-8 w-8 inline-flex items-center justify-center rounded-md text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition">&times;</button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-semibold text-zinc-700 mb-1.5">Date</label>
                        <input type="date" id="block-date" class="w-full h-10 border border-zinc-300 rounded-md px-3 text-sm bg-white outline-none focus:border-zinc-900 transition">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-zinc-700 mb-1.5">Reason</label>
                        <input type="text" id="block-reason" placeholder="e.g., Holiday, Emergency" class="w-full h-10 border border-zinc-300 rounded-md px-3 text-sm bg-white outline-none focus:border-zinc-900 transition">
                    </div>
                    <div class="pt-1">
                        <button type="button" onclick="submitDateBlock()" class="w-full h-10 rounded-md bg-zinc-900 text-white text-xs font-semibold hover:bg-zinc-800 transition">Confirm Block</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL 3: CUSTOMER PROFILE -->
    <div id="customer-modal" class="modal-overlay">
        <div class="modal-content" style="max-width: 760px;">
            <div class="p-3 sm:p-6">
                <div class="flex justify-between items-start gap-2 mb-3">
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-primary break-words leading-tight" id="cm-name">Customer</h3>
                    </div>
                    <button onclick="closeModal('customer-modal')" class="h-8 w-8 inline-flex items-center justify-center rounded-md text-zinc-400 hover:text-primary hover:bg-zinc-100 transition">&times;</button>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mb-3">
                    <div class="bg-zinc-50 border border-zinc-200 rounded-lg p-2.5">
                        <div class="text-[10px] uppercase font-bold text-zinc-400">Bookings</div>
                        <div class="text-base sm:text-lg font-bold text-primary leading-tight" id="cm-bookings">0</div>
                    </div>
                    <div class="bg-zinc-50 border border-zinc-200 rounded-lg p-2.5">
                        <div class="text-[10px] uppercase font-bold text-zinc-400"><span class="sm:hidden">Total</span><span class="hidden sm:inline">Total Value</span></div>
                        <div class="text-base sm:text-lg font-bold text-primary leading-tight" id="cm-total">₱0.00</div>
                    </div>
                    <div class="bg-zinc-50 border border-zinc-200 rounded-lg p-2.5 col-span-2 sm:col-span-1">
                        <div class="text-[10px] uppercase font-bold text-zinc-400"><span class="sm:hidden">Last</span><span class="hidden sm:inline">Last Activity</span></div>
                        <div class="text-[11px] font-medium text-primary mt-0.5 leading-tight" id="cm-last">—</div>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2 mb-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-400"><span class="sm:hidden">Timeline</span><span class="hidden sm:inline">Booking Timeline</span></h4>
                    <button
                        id="cm-export-history"
                        type="button"
                        onclick="exportCustomerHistoryCSV()"
                        class="px-2.5 py-1.5 text-[10px] font-bold rounded-lg border border-zinc-200 bg-white hover:bg-zinc-50 transition"
                    >
                        Export CSV
                    </button>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-12 gap-2 mb-3">
                    <div class="sm:col-span-4">
                        <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">Status</label>
                        <select id="cm-filter-status" class="w-full border border-zinc-200 rounded-lg px-2 py-2 text-xs bg-white">
                            <option value="all">All</option>
                            <option value="pending">Pending</option>
                            <option value="site visit">Site Visit</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="fabrication">Fabrication</option>
                            <option value="installation">Installation</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="void">Void</option>
                        </select>
                    </div>
                    <div class="sm:col-span-4">
                        <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">From</label>
                        <input id="cm-filter-from" type="date" class="w-full border border-zinc-200 rounded-lg px-2 py-2 text-xs bg-white">
                    </div>
                    <div class="sm:col-span-4">
                        <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">To</label>
                        <input id="cm-filter-to" type="date" class="w-full border border-zinc-200 rounded-lg px-2 py-2 text-xs bg-white">
                    </div>
                </div>
                <div class="space-y-2 max-h-[38vh] sm:max-h-[320px] overflow-y-auto pr-1" id="cm-history"></div>
            </div>
        </div>
    </div>

    <!-- MODAL 4: PRODUCT PRICE EDIT -->
    <div id="product-modal" class="modal-overlay">
        <div class="modal-content" style="max-width: 520px;">
            <div class="p-6">
                <div class="flex justify-between items-start mb-5">
                    <div>
                        <h3 class="text-lg font-bold text-primary" id="pm-title">Edit Product Price</h3>
                        <p class="text-xs text-zinc-500 mt-1" id="pm-subtitle">Variant pricing details</p>
                    </div>
                    <button onclick="closeModal('product-modal')" class="text-zinc-400 hover:text-primary">&times;</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-semibold text-zinc-700 mb-1.5">Base Price (per sqft)</label>
                        <input type="number" id="pm-price-no-screen" min="0.01" step="0.01" class="w-full h-10 border border-zinc-300 rounded-md px-3 text-sm bg-white outline-none focus:border-zinc-900 transition">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-zinc-700 mb-1.5">Product Type</label>
                        <select id="pm-product-type" class="w-full h-10 border border-zinc-300 rounded-md px-3 text-sm bg-white outline-none focus:border-zinc-900 transition">
                            <option value="windows">Windows</option>
                            <option value="doors">Doors</option>
                            <option value="sliding">Sliding</option>
                            <option value="partitions">Partitions</option>
                            <option value="railings">Railings</option>
                            <option value="accessories">Accessories</option>
                            <option value="others">Others</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-xs text-zinc-700">
                        <input type="checkbox" id="pm-is-available">
                        <span>Variant is available for quotation</span>
                    </label>
                    <div class="pt-2">
                        <button onclick="saveProductPrice()" class="w-full h-10 rounded-md bg-zinc-900 text-white text-xs font-semibold hover:bg-zinc-800 transition">Save Price Update</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL 5: PRODUCT CREATE -->
    <div id="product-create-modal" class="modal-overlay">
        <div class="modal-content" style="max-width: 520px;">
            <div class="p-6">
                <div class="flex justify-between items-start mb-5">
                    <div>
                        <h3 class="text-lg font-bold text-primary">Add Product Variant</h3>
                        <p class="text-xs text-zinc-500 mt-1">Create a new product or variant for quotation pricing.</p>
                    </div>
                    <button onclick="closeModal('product-create-modal')" class="text-zinc-400 hover:text-primary">&times;</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-semibold text-zinc-700 mb-1.5">Product Name</label>
                        <input type="text" id="pc-product-name" placeholder="e.g., 900 Series (Sliding Window)" class="w-full h-10 border border-zinc-300 rounded-md px-3 text-sm bg-white outline-none focus:border-zinc-900 transition">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-zinc-700 mb-1.5">Variant Label</label>
                        <input type="text" id="pc-variant-label" placeholder="e.g., White - 6mm Tempered Clear" class="w-full h-10 border border-zinc-300 rounded-md px-3 text-sm bg-white outline-none focus:border-zinc-900 transition">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-semibold text-zinc-700 mb-1.5">Base Price (per sqft)</label>
                            <input type="number" id="pc-price-no-screen" min="0.01" step="0.01" class="w-full h-10 border border-zinc-300 rounded-md px-3 text-sm bg-white outline-none focus:border-zinc-900 transition">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-zinc-700 mb-1.5">Product Type</label>
                            <select id="pc-product-type" class="w-full h-10 border border-zinc-300 rounded-md px-3 text-sm bg-white outline-none focus:border-zinc-900 transition">
                                <option value="windows">Windows</option>
                                <option value="doors">Doors</option>
                                <option value="sliding">Sliding</option>
                                <option value="partitions">Partitions</option>
                                <option value="railings">Railings</option>
                                <option value="accessories">Accessories</option>
                                <option value="others" selected>Others</option>
                            </select>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-xs text-zinc-700">
                        <input type="checkbox" id="pc-is-available" checked>
                        <span>Variant is available for quotation</span>
                    </label>
                    <div class="pt-2">
                        <button onclick="saveProductCreate()" class="w-full h-10 rounded-md bg-zinc-900 text-white text-xs font-semibold hover:bg-zinc-800 transition">Create Product Variant</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL 6: SECURITY -->
    <div id="security-modal" class="modal-overlay" onclick="closeModal('security-modal')">
        <div class="modal-content" style="max-width: 540px;" onclick="event.stopPropagation()">
            <div class="p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-primary">Security Alerts</h3>
                        <p class="text-xs text-zinc-500 mt-1">Recent admin login activity and session controls.</p>
                    </div>
                    <button onclick="closeModal('security-modal')" class="text-zinc-400 hover:text-primary">&times;</button>
                </div>
                <div class="flex items-center justify-end gap-2 mb-4">
                    <button onclick="loadLoginAlerts()" class="h-9 px-3 rounded-md border border-zinc-300 bg-white text-zinc-700 text-xs font-semibold hover:bg-zinc-50 transition">Refresh</button>
                    <button onclick="logoutAllSessions()" class="h-9 px-3 rounded-md bg-zinc-900 text-white text-xs font-semibold hover:bg-zinc-800 transition">Logout all sessions</button>
                </div>
                <div id="security-login-alerts" class="max-h-[360px] overflow-y-auto space-y-2 pr-1"></div>
            </div>
        </div>
    </div>

    <div id="product-delete-selected-modal" class="modal-overlay" onclick="handleProductDeleteSelectedBackdrop(event)">
        <div class="modal-content" style="max-width: 430px;" onclick="event.stopPropagation()">
            <div class="p-6">
                <div class="w-12 h-12 rounded-full bg-red-50 text-red-600 flex items-center justify-center mb-4 mx-auto">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 9v4"></path>
                        <path d="M12 17h.01"></path>
                        <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"></path>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-zinc-900 text-center mb-1">Delete <span id="pdsm-count">0</span> selected variant(s)?</h3>
                <p class="text-xs text-zinc-500 text-center mb-6">This action cannot be undone.</p>
                <div class="flex items-center justify-center gap-2">
                    <button type="button" onclick="resolveProductDeleteSelectedModal(false)" class="h-9 px-4 rounded-md border border-zinc-300 bg-white text-zinc-700 text-xs font-semibold hover:bg-zinc-50 transition">Cancel</button>
                    <button type="button" onclick="resolveProductDeleteSelectedModal(true)" class="h-9 px-4 rounded-md bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition">Delete Selected</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const CSRF_TOKEN = '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8"); ?>';
        const STATUS_LABELS = <?php echo json_encode($statusLabels, JSON_UNESCAPED_SLASHES); ?>;
        const WORKFLOW_TRANSITIONS = <?php echo json_encode($workflowTransitions, JSON_UNESCAPED_SLASHES); ?>;
        const TERMINAL_STATUSES = <?php echo json_encode($terminalStatuses, JSON_UNESCAPED_SLASHES); ?>;
        let GLOBAL_DATA = [];
        let FILTER_STATUS = 'All';
        let FILTER_SEARCH = '';
        let SORT_ORDER = 'newest';
        const ROWS_PER_PAGE = 10;
        const CUSTOMER_ROWS_PER_PAGE = 10;
        let CURRENT_PAGE = 1;
        let CUSTOMER_SEARCH = '';
        let CUSTOMER_PAGE = 1;
        let CUSTOMER_INDEX = {};
        let ACTIVE_CUSTOMER = null;
        let CUSTOMER_HISTORY_FILTER_STATUS = 'all';
        let CUSTOMER_HISTORY_FILTER_FROM = '';
        let CUSTOMER_HISTORY_FILTER_TO = '';
        let AUDIT_FILTER_ACTION = 'all';
        let AUDIT_FILTER_ACTOR = 'all';
        let AUDIT_FILTER_FROM = '';
        let AUDIT_FILTER_TO = '';
        let ACTIVE_ID = null;
        let PRODUCT_DATA = [];
        let PRODUCT_PAGE = 1;
        const PRODUCT_ROWS_PER_PAGE = 10;
        let PRODUCT_SEARCH = '';
        let PRODUCT_TYPE_FILTER = 'all';
        let ACTIVE_PRODUCT_ITEM = null;
        let SELECTED_PRODUCT_KEYS = new Set();
        let PRODUCT_DELETE_SELECTED_RESOLVER = null;
        let LOGIN_ALERTS = [];
        let calendar;
        let wsClient = null;
        let wsReconnectTimer = null;
        let realtimeRefreshTimer = null;
        let BLOCK_MODAL_IGNORE_BACKDROP_ONCE = false;
        let DATA_STATE = 'loading'; // loading | ready | error
        let DATA_ERROR = '';
        const WS_URL = `${window.location.protocol === 'https:' ? 'wss' : 'ws'}://${window.location.hostname}:8081`;

        document.addEventListener('DOMContentLoaded', () => {
            initApp();
            initCalendar();
            fetch('backend/check_expiry.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': CSRF_TOKEN }
            });
        });

        
        function switchView(viewId) {
            document.querySelectorAll('main > div').forEach(div => div.classList.add('hidden-section'));
            const targetView = document.getElementById('view-' + viewId);
            if (targetView) targetView.classList.remove('hidden-section');
            
            document.querySelectorAll('#main-nav [data-view], #main-nav-mobile [data-view]').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelectorAll(`#main-nav [data-view="${viewId}"], #main-nav-mobile [data-view="${viewId}"]`).forEach(btn => {
                btn.classList.add('active');
            });
            centerMobileNavButton(viewId);
            updateMobileProductFab(viewId);

            if(viewId === 'calendar' && calendar) {
                setTimeout(() => { 
                    calendar.updateSize(); 
                    calendar.refetchEvents();
                }, 50); 
            }
            if(viewId === 'kanban') renderKanban();
            if(viewId === 'database') {
                renderCustomerDB();
                loadRetentionStatus();
            }
            if(viewId === 'products') renderProductTable();
        }

        function updateMobileProductFab(viewId) {
            const fab = document.getElementById('mobile-add-variant-fab');
            const isMobile = window.innerWidth < 768;
            const show = isMobile && viewId === 'products';
            if (fab) fab.classList.toggle('hidden', !show);
            updateMobileSelectedActionFab();
        }

        function centerMobileNavButton(viewId) {
            if (window.innerWidth >= 768) return;
            const nav = document.getElementById('main-nav-mobile');
            if (!nav) return;
            const btn = nav.querySelector(`[data-view="${viewId}"]`);
            if (!btn) return;
            const targetLeft = btn.offsetLeft - ((nav.clientWidth - btn.clientWidth) / 2);
            nav.scrollTo({
                left: Math.max(0, targetLeft),
                behavior: 'smooth'
            });
        }
        
        async function initApp() {
            document.querySelectorAll('.filter-btn').forEach(btn => btn.addEventListener('click', e => {
                document.querySelectorAll('.filter-btn').forEach(b => {
                    b.classList.remove('bg-white', 'shadow-sm', 'text-primary');
                    b.classList.add('text-zinc-500');
                });
                e.target.classList.remove('text-zinc-500');
                e.target.classList.add('bg-white', 'shadow-sm', 'text-primary');
                FILTER_STATUS = e.target.dataset.filter;
                CURRENT_PAGE = 1;
                renderTable();
            }));
            
            document.getElementById('search-box').addEventListener('input', e => {
                FILTER_SEARCH = e.target.value.toLowerCase();
                CURRENT_PAGE = 1;
                renderTable();
            });

            document.getElementById('sort-order').addEventListener('change', e => {
                SORT_ORDER = e.target.value;
                CURRENT_PAGE = 1;
                renderTable();
            });

            const customerSearch = document.getElementById('customer-search');
            if (customerSearch) {
                customerSearch.addEventListener('input', (e) => {
                    CUSTOMER_SEARCH = String(e.target.value || '').toLowerCase();
                    CUSTOMER_PAGE = 1;
                    renderCustomerDB();
                });
            }
            const productSearch = document.getElementById('product-search');
            if (productSearch) {
                productSearch.addEventListener('input', (e) => {
                    PRODUCT_SEARCH = String(e.target.value || '').toLowerCase();
                    PRODUCT_PAGE = 1;
                    renderProductTable();
                });
            }
            const productTypeFilter = document.getElementById('product-type-filter');
            if (productTypeFilter) {
                productTypeFilter.addEventListener('change', (e) => {
                    PRODUCT_TYPE_FILTER = String(e.target.value || 'all').trim().toLowerCase();
                    PRODUCT_PAGE = 1;
                    renderProductTable();
                });
            }
            bindCustomerHistoryFilters();
            bindAuditFilters();
            updateMobileProductFab('overview');
            loadRetentionStatus();
            setupModalGuards();
            window.addEventListener('resize', () => {
                const activeBtn = document.querySelector('#main-nav-mobile [data-view].active, #main-nav [data-view].active');
                const activeView = activeBtn ? String(activeBtn.dataset.view || 'overview') : 'overview';
                updateMobileProductFab(activeView);
            });

            await fetchLatestData();
            await fetchProductData();
            await loadLoginAlerts({ silent: true });
            setInterval(() => fetchLatestData({ silent: true }), 30000);
            setInterval(() => loadLoginAlerts({ silent: true }), 30000);
            startRealtime();
        }

        function setLiveIndicatorState(state) {
            const dot = document.getElementById('live-indicator');
            if (!dot) return;
            dot.classList.remove('bg-emerald-500', 'bg-amber-500', 'bg-red-500');
            if (state === 'online') dot.classList.add('bg-emerald-500');
            else if (state === 'degraded') dot.classList.add('bg-amber-500');
            else dot.classList.add('bg-red-500');
        }

        function queueRealtimeRefresh() {
            if (realtimeRefreshTimer) return;
            realtimeRefreshTimer = setTimeout(async () => {
                realtimeRefreshTimer = null;
                await fetchLatestData({ silent: true });
            }, 350);
        }

        function startRealtime() {
            if (wsClient && (wsClient.readyState === WebSocket.OPEN || wsClient.readyState === WebSocket.CONNECTING)) {
                return;
            }

            connectRealtime();
        }

        async function connectRealtime() {
            let token = '';
            try {
                const tokenRes = await fetch('backend/ws_token.php?action=admin_dashboard', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({})
                });
                const tokenJson = await tokenRes.json();
                if (!tokenRes.ok || tokenJson.status !== 'success' || !tokenJson.token) {
                    throw new Error(tokenJson.message || 'Token request failed.');
                }
                token = tokenJson.token;
                wsClient = new WebSocket(`${WS_URL}?token=${encodeURIComponent(token)}`);
            } catch (e) {
                setLiveIndicatorState('degraded');
                document.getElementById('last-updated').textContent = 'Polling fallback';
                scheduleRealtimeReconnect();
                return;
            }

            wsClient.addEventListener('open', () => {
                setLiveIndicatorState('online');
                document.getElementById('last-updated').textContent = 'Realtime';
            });

            wsClient.addEventListener('message', (event) => {
                try {
                    const payload = JSON.parse(event.data);
                    if (payload && payload.type) {
                        queueRealtimeRefresh();
                    }
                } catch (e) {
                }
            });

            wsClient.addEventListener('close', () => {
                setLiveIndicatorState('degraded');
                document.getElementById('last-updated').textContent = 'Polling fallback';
                scheduleRealtimeReconnect();
            });

            wsClient.addEventListener('error', () => {
                setLiveIndicatorState('degraded');
                document.getElementById('last-updated').textContent = 'Polling fallback';
            });
        }

        function scheduleRealtimeReconnect() {
            if (wsReconnectTimer) return;
            wsReconnectTimer = setTimeout(() => {
                wsReconnectTimer = null;
                startRealtime();
            }, 3000);
        }

        function initCalendar() {
            const calendarEl = document.getElementById('calendar');
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek' },
                events: 'backend/fetch_calendar.php', 
                loading: function(isLoading) {
                    if (isLoading) {
                        setCalendarState('loading', 'Loading schedule...');
                    } else {
                        setCalendarState('ready', '');
                    }
                },
                eventSourceFailure: function() {
                    setCalendarState('error', 'Failed to load schedule. ');
                },
                eventClick: function(info) {
                    if (info.event.extendedProps.type === 'booking') {
                        openModal(info.event.id);
                    }
                },
                dateClick: function(info) {
                    openCalendarDateBookings(info.dateStr);
                },
                datesSet: function() {
                    const el = document.getElementById('calendar');
                    if (!el) return;
                    el.classList.remove('calendar-transition-in');
                    void el.offsetWidth;
                    el.classList.add('calendar-transition-in');
                },
                eventContent: function(arg) {
                    const isMobile = window.matchMedia('(max-width: 767px)').matches;
                    const isBooking = String(arg.event.extendedProps?.type || '') === 'booking';
                    if (!isBooking) return;

                    const status = String(arg.event.extendedProps?.status || '').trim();
                    const colorMap = {
                        'Pending': '#3b82f6',
                        'Site Visit': '#06b6d4',
                        'Confirmed': '#10b981',
                        'Fabrication': '#f59e0b',
                        'Installation': '#8b5cf6',
                        'Completed': '#374151'
                    };
                    const dotColor = colorMap[status] || '#3b82f6';
                    const title = escapeHtml(status || 'Pending');
                    if (!isMobile) {
                        return {
                            html: `<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold text-white" style="background:${dotColor}">${title}</span>`
                        };
                    }
                    return {
                        html: `<span class="inline-block h-2.5 w-2.5 rounded-full align-middle" style="background:${dotColor}" title="${title}" aria-label="${title}"></span>`
                    };
                },
                eventDidMount: function(info) {
                    const isMobile = window.matchMedia('(max-width: 767px)').matches;
                    const isBooking = String(info.event.extendedProps?.type || '') === 'booking';
                    if (!isMobile || !isBooking) return;
                    const dot = info.el.querySelector('span[aria-label]');
                    if (dot) {
                        info.el.innerHTML = '';
                        info.el.appendChild(dot);
                    } else {
                        info.el.textContent = '';
                    }
                    info.el.style.padding = '0';
                    info.el.style.margin = '0';
                    info.el.style.background = 'transparent';
                    info.el.style.border = 'none';
                    info.el.style.width = '100%';
                    info.el.style.display = 'flex';
                    info.el.style.justifyContent = 'flex-end';
                    info.el.style.alignItems = 'center';
                    info.el.style.paddingRight = '6px';
                },
                height: '100%',
                themeSystem: 'standard'
            });
            calendar.render();
        }

        function normalizeDateKey(raw) {
            const v = String(raw || '').trim();
            if (!v) return '';
            const m = v.match(/^(\d{4})-(\d{2})-(\d{2})/);
            if (m) return `${m[1]}-${m[2]}-${m[3]}`;
            const ts = Date.parse(v);
            if (Number.isNaN(ts)) return '';
            const d = new Date(ts);
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        }

        async function openCalendarDateBookings(dateStr) {
            const dateKey = normalizeDateKey(dateStr);
            if (!dateKey) return;
            const rows = (Array.isArray(GLOBAL_DATA) ? GLOBAL_DATA : []).filter((row) => normalizeDateKey(row.install_date) === dateKey);
            const blockedReason = getBlockedReasonForDate(dateKey);
            const prettyDate = new Date(`${dateKey}T00:00:00`).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });

            if (rows.length === 0) {
                if (blockedReason) {
                    const res = await Swal.fire({
                        title: `Bookings on ${prettyDate}`,
                        html: `<div class="text-left space-y-2"><p class="text-sm text-zinc-700">No bookings scheduled for this date.</p><p class="text-xs text-zinc-500">Date is currently blocked${blockedReason ? `: <span class="font-semibold text-zinc-700">${escapeHtml(blockedReason)}</span>` : '.'}</p></div>`,
                        showCancelButton: true,
                        showConfirmButton: true,
                        confirmButtonText: 'Unblock Date',
                        cancelButtonText: 'Close',
                        buttonsStyling: false,
                        customClass: {
                            popup: 'rounded-xl border border-zinc-200 shadow-2xl p-0',
                            title: 'text-left text-lg font-semibold text-zinc-900 px-6 pt-6 pb-0',
                            htmlContainer: 'px-6 pt-3 pb-0',
                            actions: 'w-full px-6 pb-6 pt-4 justify-end gap-2',
                            confirmButton: 'h-9 px-4 rounded-md bg-zinc-900 text-white text-xs font-semibold hover:bg-zinc-800 transition',
                            cancelButton: 'h-9 px-4 rounded-md border border-zinc-300 bg-white text-zinc-700 text-xs font-semibold hover:bg-zinc-50 transition'
                        }
                    });
                    if (res.isConfirmed) {
                        await unblockCalendarDate(dateKey);
                    }
                    return;
                }
                await Swal.fire({
                    title: `Bookings on ${prettyDate}`,
                    html: `<p class="text-sm text-zinc-600">No bookings scheduled for this date.</p>`,
                    showCancelButton: true,
                    showConfirmButton: false,
                    cancelButtonText: 'Close',
                    buttonsStyling: false,
                    customClass: {
                        popup: 'rounded-xl border border-zinc-200 shadow-2xl p-0',
                        title: 'text-left text-lg font-semibold text-zinc-900 px-6 pt-6 pb-0',
                        htmlContainer: 'px-6 pt-3 pb-0',
                        actions: 'w-full px-6 pb-6 pt-4 justify-end',
                        cancelButton: 'h-9 px-4 rounded-md border border-zinc-300 bg-white text-zinc-700 text-xs font-semibold hover:bg-zinc-50 transition'
                    }
                });
                return;
            }

            const itemsHtml = rows.map((row) => {
                const id = escapeHtml(row.id || '—');
                const customer = escapeHtml(row.customer_name_snapshot || row.customer || row.name || 'Guest');
                const product = escapeHtml(((row.product || '—').split('(')[0] || '—').trim());
                const status = escapeHtml(row.status || 'Pending');
                const price = parseFloat(row.price) || 0;
                return `
                    <button type="button" data-booking-id="${id}" class="date-booking-item w-full text-left p-3 rounded-lg border border-zinc-200 bg-white hover:bg-zinc-50 transition">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[11px] font-mono text-zinc-500">${id}</span>
                            <span class="text-[10px] px-2 py-0.5 rounded-full border border-zinc-200 text-zinc-600">${status}</span>
                        </div>
                        <div class="text-sm font-semibold text-primary mt-1">${customer}</div>
                        <div class="text-[11px] text-zinc-500 mt-0.5">${product}</div>
                        <div class="text-[11px] font-mono text-zinc-700 mt-1">₱${price.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                    </button>
                `;
            }).join('');

            await Swal.fire({
                title: `Bookings on ${prettyDate}`,
                html: `<div class="space-y-2 max-h-[55vh] overflow-y-auto">${itemsHtml}</div>`,
                showCancelButton: true,
                showConfirmButton: false,
                cancelButtonText: 'Close',
                buttonsStyling: false,
                customClass: {
                    popup: 'rounded-xl border border-zinc-200 shadow-2xl p-0',
                    title: 'text-left text-lg font-semibold text-zinc-900 px-6 pt-6 pb-0',
                    htmlContainer: 'px-6 pt-3 pb-0',
                    actions: 'w-full px-6 pb-6 pt-4 justify-end',
                    cancelButton: 'h-9 px-4 rounded-md border border-zinc-300 bg-white text-zinc-700 text-xs font-semibold hover:bg-zinc-50 transition'
                },
                didOpen: () => {
                    document.querySelectorAll('.date-booking-item').forEach((btn) => {
                        btn.addEventListener('click', () => {
                            const bookingId = String(btn.getAttribute('data-booking-id') || '').trim();
                            if (!bookingId) return;
                            Swal.close();
                            openModal(bookingId);
                        });
                    });
                }
            });
        }

        function getBlockedReasonForDate(dateKey) {
            if (!calendar || !dateKey) return '';
            const target = normalizeDateKey(dateKey);
            const blockedEvent = calendar.getEvents().find((evt) => {
                const type = String(evt.extendedProps?.type || '').toLowerCase();
                if (type !== 'blocked') return false;
                return normalizeDateKey(evt.startStr || evt.start) === target;
            });
            if (!blockedEvent) return '';
            return String(blockedEvent.extendedProps?.reason || blockedEvent.title || '').trim();
        }

        async function unblockCalendarDate(dateKey) {
            try {
                const formData = new FormData();
                formData.append('date', dateKey);
                const req = await fetch('backend/unblock_date.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': CSRF_TOKEN },
                    body: formData
                });
                const res = await req.json();
                if (!res || res.status !== 'success') {
                    throw new Error((res && res.message) ? res.message : 'Failed to unblock date.');
                }
                showCustomAlert('success', 'Date Unblocked', `${dateKey} is now open for booking.`);
                if (calendar) {
                    calendar.refetchEvents();
                }
                await fetchLatestData({ silent: true });
            } catch (err) {
                showCustomAlert('error', 'Unblock Failed', err.message || 'Failed to unblock date.');
            }
        }

        function setupModalGuards() {
            const blockOverlay = document.getElementById('block-modal');
            const blockContent = document.getElementById('block-modal-content');
            if (blockOverlay) {
                const maybeCloseBlockModal = (e) => {
                    if (e.target !== blockOverlay) return;
                    if (BLOCK_MODAL_IGNORE_BACKDROP_ONCE) {
                        BLOCK_MODAL_IGNORE_BACKDROP_ONCE = false;
                        return;
                    }
                    closeModal('block-modal');
                };
                blockOverlay.addEventListener('click', maybeCloseBlockModal);
                blockOverlay.addEventListener('touchend', maybeCloseBlockModal, { passive: true });
            }
            if (blockContent) {
                blockContent.addEventListener('click', (e) => e.stopPropagation());
                blockContent.addEventListener('touchstart', (e) => e.stopPropagation(), { passive: true });
                blockContent.addEventListener('touchend', (e) => e.stopPropagation(), { passive: true });
            }
        }

        function setCalendarState(type, message) {
            const el = document.getElementById('calendar-state-msg');
            if (!el) return;
            if (type === 'ready') {
                el.classList.add('hidden');
                el.innerHTML = '';
                return;
            }
            el.classList.remove('hidden');
            if (type === 'error') {
                el.innerHTML = `<span class="text-red-500">${message || 'Schedule unavailable.'}</span><button onclick="retryDataLoad()" class="ml-2 px-2 py-1 border border-zinc-300 rounded-md text-[10px] font-bold hover:border-primary hover:text-primary transition">Retry</button>`;
                return;
            }
            el.innerHTML = `<span class="text-zinc-500">${message || 'Loading schedule...'}</span>`;
        }

        function stateMessageHTML(title, detail, retry = false, compact = false) {
            const pad = compact ? 'py-6' : 'py-10';
            const titleClass = compact ? 'text-xs' : 'text-sm';
            const detailClass = compact ? 'text-[11px]' : 'text-xs';
            return `<div class="px-6 ${pad} text-center">
                <p class="${titleClass} font-bold text-zinc-700">${title}</p>
                <p class="${detailClass} text-zinc-500 mt-1">${detail}</p>
                ${retry ? `<button onclick="retryDataLoad()" class="mt-3 px-3 py-1.5 border border-zinc-300 rounded-lg text-[10px] font-bold hover:border-primary hover:text-primary transition">Retry</button>` : ''}
            </div>`;
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function renderOverviewWidgetsState() {
            const demand = document.getElementById('demand-list');
            const demandMobile = document.getElementById('demand-list-mobile');
            const audit = document.getElementById('audit-log-widget');
            const security = document.getElementById('security-login-alerts');
            if (DATA_STATE === 'loading') {
                if (demand) demand.innerHTML = `<div class="space-y-2">${'<div class="h-2 bg-zinc-100 rounded animate-pulse"></div>'.repeat(4)}</div>`;
                if (demandMobile) demandMobile.innerHTML = `<div class="space-y-2">${'<div class="h-2 bg-zinc-100 rounded animate-pulse"></div>'.repeat(2)}</div>`;
                if (audit) audit.innerHTML = `<div class="space-y-3">${'<div class="h-3 bg-zinc-100 rounded animate-pulse"></div>'.repeat(4)}</div>`;
                if (security) security.innerHTML = `<div class="space-y-2">${'<div class="h-8 bg-zinc-100 rounded animate-pulse"></div>'.repeat(4)}</div>`;
                return;
            }
            if (DATA_STATE === 'error') {
                if (demand) demand.innerHTML = stateMessageHTML('Unable to load products', 'Check connection then retry.', true, true);
                if (demandMobile) demandMobile.innerHTML = stateMessageHTML('Unable to load products', 'Check connection then retry.', true, true);
                if (audit) audit.innerHTML = stateMessageHTML('Unable to load audit logs', DATA_ERROR || 'Failed to fetch latest records.', true, true);
            }
        }

        async function loadLoginAlerts(options = {}) {
            const silent = Boolean(options.silent);
            try {
                const req = await fetch('backend/auth.php?action=login_alerts&limit=30', {
                    method: 'GET',
                    headers: { 'X-CSRF-Token': CSRF_TOKEN }
                });
                const res = await req.json();
                if (!req.ok || !res || res.status !== 'success' || !Array.isArray(res.alerts)) {
                    throw new Error((res && res.message) ? res.message : 'Failed to load login alerts.');
                }
                LOGIN_ALERTS = res.alerts;
                renderLoginAlertsWidget();
            } catch (err) {
                if (!silent) {
                    showCustomAlert('error', 'Security Alerts Unavailable', err.message || 'Failed to load login alerts.');
                }
            }
        }

        function renderLoginAlertsWidget() {
            const host = document.getElementById('security-login-alerts');
            if (!host) return;
            if (!Array.isArray(LOGIN_ALERTS) || LOGIN_ALERTS.length === 0) {
                host.innerHTML = `<div class="text-xs text-zinc-500 border border-zinc-200 rounded-lg p-3">No recent login alerts.</div>`;
                return;
            }

            const labelMap = {
                login_success: 'Login Success',
                login_failed: 'Invalid Login Attempt',
                login_totp_failed: 'TOTP Failed',
                login_locked: 'Login Locked',
                login_captcha_failed: 'Captcha Failed',
                login_ip_rate_limited: 'IP Rate Limited',
                logout_all_sessions: 'All Sessions Logged Out'
            };
            const levelClass = {
                info: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                warning: 'bg-amber-50 text-amber-700 border-amber-200',
                error: 'bg-red-50 text-red-700 border-red-200'
            };

            host.innerHTML = LOGIN_ALERTS.slice(0, 30).map((entry) => {
                const sev = String(entry.severity || 'info').toLowerCase();
                const ev = String(entry.event || '').trim();
                const title = labelMap[ev] || ev.replace(/[_-]+/g, ' ').replace(/\b\w/g, (m) => m.toUpperCase()) || 'Security Event';
                const user = escapeHtml(entry.username || 'admin');
                const ip = escapeHtml(entry.ip || 'unknown');
                const details = escapeHtml(entry.details || '');
                const when = Number(entry.ts || 0) > 0 ? new Date(Number(entry.ts) * 1000).toLocaleString() : 'Recent';
                const badgeClass = levelClass[sev] || levelClass.info;
                return `<div class="border border-zinc-200 rounded-lg p-3 bg-white">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <span class="text-xs font-semibold text-zinc-900">${escapeHtml(title)}</span>
                        <span class="text-[10px] px-2 py-0.5 rounded-full border ${badgeClass}">${escapeHtml(sev)}</span>
                    </div>
                    <div class="text-[11px] text-zinc-600">${when}</div>
                    <div class="text-[11px] text-zinc-600">User: ${user} • IP: ${ip}</div>
                    ${details ? `<div class="text-[11px] text-zinc-500 mt-0.5">${details}</div>` : ''}
                </div>`;
            }).join('');
        }

        function openSecurityModal() {
            renderLoginAlertsWidget();
            document.getElementById('security-modal').classList.add('open');
            loadLoginAlerts({ silent: true });
        }

        async function logoutAllSessions() {
            const confirmRes = await Swal.fire({
                title: 'Logout all sessions?',
                html: '<p class="text-sm text-zinc-600">This will force logout every active admin session, including this device.</p>',
                showCancelButton: true,
                confirmButtonText: 'Logout all',
                cancelButtonText: 'Cancel',
                buttonsStyling: false,
                customClass: {
                    popup: 'rounded-xl border border-zinc-200 shadow-2xl p-0',
                    title: 'text-left text-lg font-semibold text-zinc-900 px-6 pt-6 pb-0',
                    htmlContainer: 'px-6 pt-3 pb-0',
                    actions: 'w-full px-6 pb-6 pt-4 gap-2 justify-end',
                    confirmButton: 'h-9 px-4 rounded-md bg-zinc-900 text-white text-xs font-semibold hover:bg-zinc-800 transition',
                    cancelButton: 'h-9 px-4 rounded-md border border-zinc-300 bg-white text-zinc-700 text-xs font-semibold hover:bg-zinc-50 transition'
                }
            });
            if (!confirmRes.isConfirmed) return;
            try {
                const req = await fetch('backend/auth.php?action=logout_all_sessions', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({})
                });
                const res = await req.json();
                if (!req.ok || !res || res.status !== 'success') {
                    throw new Error((res && res.message) ? res.message : 'Failed to logout all sessions.');
                }
                window.location.href = 'admin-login.html';
            } catch (err) {
                showCustomAlert('error', 'Logout All Failed', err.message || 'Unable to logout sessions.');
            }
        }

        function retryDataLoad() {
            fetchLatestData();
            fetchProductData();
        }

        async function fetchJsonWithTimeout(url, options = {}, timeoutMs = 12000) {
            const controller = new AbortController();
            const timer = setTimeout(() => controller.abort(), timeoutMs);
            try {
                const response = await fetch(url, { ...options, signal: controller.signal });
                const json = await response.json();
                return { response, json };
            } finally {
                clearTimeout(timer);
            }
        }

        async function fetchLatestData(options = {}) {
            const silent = Boolean(options.silent);
            if (!silent) {
                DATA_STATE = 'loading';
                DATA_ERROR = '';
                setCalendarState('loading', 'Loading schedule...');
                renderTable();
                renderKanban();
                renderCustomerDB();
                renderOverviewWidgetsState();
            }
            try {
                const { response, json } = await fetchJsonWithTimeout('backend/process.php?action=fetch_all', {
                    headers: { 'X-CSRF-Token': CSRF_TOKEN }
                }, 12000);
                let rawData = [];

                if (json && typeof json === 'object' && json.error) {
                    throw new Error(String(json.error));
                }

                rawData = (json && typeof json === 'object' && Array.isArray(json.data)) ? json.data : json;

                const normalized = Array.isArray(rawData) ? rawData : Object.values(rawData || {});
                GLOBAL_DATA = normalized
                    .filter(item => item && typeof item === 'object')
                    .map((item, idx) => {
                        if (!item.id) item.id = item.qtnId || item.booking_id || `ROW-${idx + 1}`;
                        return item;
                    })
                    .reverse();

                DATA_STATE = 'ready';
                DATA_ERROR = '';
                
                document.getElementById('last-updated').textContent = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
                refreshStats();
                renderTable();
                renderKanban();
                renderCustomerDB();
                generateAuditLog();
                if(calendar) calendar.refetchEvents();
                setCalendarState('ready', '');
            } catch (error) { 
                console.error(error);
                DATA_STATE = 'error';
                DATA_ERROR = String(error?.message || 'Unknown error');
                GLOBAL_DATA = [];
                refreshStats();
                renderTable();
                renderKanban();
                renderCustomerDB();
                generateAuditLog();
                setCalendarState('error', 'Failed to load dashboard data. ');
                setLiveIndicatorState('offline');
                document.getElementById('last-updated').textContent = 'Access blocked';
            }
        }

        function refreshStats() {
            const formatPeso = (num) => {
                const val = Number(num) || 0;
                return `₱${val.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            };
            const formatPesoCompact = (num) => {
                const val = Number(num) || 0;
                const abs = Math.abs(val);
                if (abs >= 1000000000) return `₱${(val / 1000000000).toFixed(2)}B`;
                if (abs >= 1000000) return `₱${(val / 1000000).toFixed(2)}M`;
                if (abs >= 1000) return `₱${(val / 1000).toFixed(2)}K`;
                return formatPeso(val);
            };
            let secured = 0, pipeline = 0, active = 0, productTallies = {};
            GLOBAL_DATA.forEach(row => {
                const val = parseFloat(row.price) || 0;
                const stat = (row.status || 'Pending');
                if(['Cancelled', 'Void'].includes(stat)) return;
                if(['Confirmed', 'Fabrication', 'Installation', 'Completed'].includes(stat)) { secured += val; } else { pipeline += val; }
                if(['Fabrication', 'Installation'].includes(stat)) active++;
                const p = (row.product || 'Others').split('(')[0];
                productTallies[p] = (productTallies[p] || 0) + 1;
            });
            const total = secured + pipeline;
            const securedPct = total > 0 ? (secured / total) * 100 : 0;
            const compactMobile = window.innerWidth < 640;
            document.getElementById('rev-total').textContent = compactMobile ? formatPesoCompact(total) : formatPeso(total);
            document.getElementById('txt-secured').textContent = formatPeso(secured);
            document.getElementById('txt-pipeline').textContent = formatPeso(pipeline);
            document.getElementById('rev-bar-conf').style.width = securedPct + "%";
            document.getElementById('active-jobs').textContent = active;
            document.getElementById('pend-count').textContent = GLOBAL_DATA.filter(r => (r.status || 'Pending') === 'Pending').length;
            const widget = document.getElementById('demand-list');
            const mobileWidget = document.getElementById('demand-list-mobile');
            if (widget) widget.innerHTML = "";
            if (mobileWidget) mobileWidget.innerHTML = "";
            if (DATA_STATE === 'loading' || DATA_STATE === 'error') {
                renderOverviewWidgetsState();
                return;
            }
            if (GLOBAL_DATA.length === 0) {
                const emptyHtml = stateMessageHTML('No product activity yet', 'Bookings will appear here once customers submit.', false, true);
                if (widget) widget.innerHTML = emptyHtml;
                if (mobileWidget) mobileWidget.innerHTML = emptyHtml;
                return;
            }
            const sortedTallies = Object.entries(productTallies).sort(([,a],[,b]) => b - a);
            sortedTallies.slice(0, 4).forEach(([prod, count]) => {
                const barW = Math.max(5, (count / GLOBAL_DATA.length) * 100);
                if (widget) {
                    widget.innerHTML += `<div><div class="flex justify-between text-[10px] text-zinc-500 mb-1 font-medium"><span>${prod}</span><span>${count}</span></div><div class="w-full bg-zinc-100 rounded-full h-1.5 overflow-hidden"><div class="bg-zinc-800 h-full rounded-full" style="width: ${barW}%"></div></div></div>`;
                }
            });
            sortedTallies.slice(0, 2).forEach(([prod, count]) => {
                const barW = Math.max(5, (count / GLOBAL_DATA.length) * 100);
                if (mobileWidget) {
                    mobileWidget.innerHTML += `<div><div class="flex justify-between text-[10px] text-zinc-500 mb-1 font-medium"><span>${prod}</span><span>${count}</span></div><div class="w-full bg-zinc-100 rounded-full h-1.5 overflow-hidden"><div class="bg-zinc-800 h-full rounded-full" style="width: ${barW}%"></div></div></div>`;
                }
            });
        }
        
        function renderTable() {
            const list = document.getElementById('data-list');
            list.innerHTML = "";
            if (DATA_STATE === 'loading') {
                list.innerHTML = `<div class="px-6 py-5 space-y-3">${'<div class="h-10 rounded-lg bg-zinc-100 animate-pulse"></div>'.repeat(6)}</div>`;
                document.getElementById('record-count').textContent = 'Loading records...';
                document.getElementById('pagination-controls').innerHTML = `<span class="text-[10px] text-zinc-400">Please wait...</span>`;
                return;
            }
            if (DATA_STATE === 'error') {
                list.innerHTML = stateMessageHTML('Unable to load bookings', DATA_ERROR || 'Failed to fetch booking data.', true);
                document.getElementById('record-count').textContent = 'Load failed';
                document.getElementById('pagination-controls').innerHTML = `<span class="text-[10px] text-red-500">Retry required</span>`;
                return;
            }
            const displayData = GLOBAL_DATA.filter(item => {
                const status = (item.status || 'Pending');
                const idStr = String(item.id || '');
                const nameStr = String(item.customer_name_snapshot || item.customer || item.name || '');
                const emailStr = String(item.email || '');
                const productStr = String(item.product || '');
                const searchStr = (idStr + nameStr + emailStr + productStr).toLowerCase();
                let matchesStatus = false;
                if (FILTER_STATUS === 'All') matchesStatus = !['Cancelled', 'Void'].includes(status);
                else if (FILTER_STATUS === 'Archived') matchesStatus = ['Cancelled', 'Void'].includes(status);
                else matchesStatus = status === FILTER_STATUS;
                return matchesStatus && searchStr.includes(FILTER_SEARCH);
            });

            const getRowDate = (row) => {
                const raw = row.created_at || row.date_created || row.install_date || row.date;
                if (!raw) return 0;
                if (typeof raw === 'number' || (typeof raw === 'string' && /^[0-9]+$/.test(raw))) {
                    const num = Number(raw);
                    if (!Number.isFinite(num)) return 0;
                    return num > 100000000000 ? num : num * 1000; // ms vs seconds
                }
                const ts = Date.parse(raw);
                return Number.isNaN(ts) ? 0 : ts;
            };

            const sortedData = [...displayData].sort((a, b) => {
                const aDate = getRowDate(a);
                const bDate = getRowDate(b);
                return SORT_ORDER === 'newest' ? (bDate - aDate) : (aDate - bDate);
            });

            const totalRecords = sortedData.length;
            const totalPages = Math.max(1, Math.ceil(totalRecords / ROWS_PER_PAGE));
            if (CURRENT_PAGE > totalPages) CURRENT_PAGE = totalPages;
            const start = (CURRENT_PAGE - 1) * ROWS_PER_PAGE;
            const pageData = sortedData.slice(start, start + ROWS_PER_PAGE);

            if (totalRecords === 0) {
                list.innerHTML = stateMessageHTML('No matching bookings', 'Try changing filters or wait for new inquiries.');
                document.getElementById('record-count').textContent = '0 records';
                document.getElementById('pagination-controls').innerHTML = `<span class="text-[10px] text-zinc-400">No pages</span>`;
                return;
            }

            document.getElementById('record-count').textContent = `${totalRecords} records • Page ${CURRENT_PAGE} of ${totalPages}`;
            renderPaginationControls(totalPages);

            pageData.forEach((row) => {
                let badge = "bg-zinc-100 text-zinc-600 border-zinc-200";
                const s = (row.status || 'Pending');
                if(s === 'Confirmed') badge = "bg-emerald-50 text-emerald-600 border-emerald-100";
                if(s === 'Fabrication') badge = "bg-orange-50 text-orange-600 border-orange-100";
                if(s === 'Installation') badge = "bg-purple-50 text-purple-600 border-purple-100";
                if(s === 'Cancelled' || s === 'Void') badge = "bg-red-50 text-red-600 border-red-100";
                const custName = row.customer_name_snapshot || row.customer || row.name || 'Guest';
                list.innerHTML += `<div class="grid grid-cols-12 min-w-[760px] px-6 py-3 items-center text-xs table-row-anim border-l-2 border-transparent hover:border-primary group cursor-pointer" onclick="openModal('${row.id}')"><div class="col-span-2 font-mono text-zinc-500">${row.id}</div><div class="col-span-3"><div class="font-bold text-primary truncate pr-2">${custName}</div><div class="text-[10px] text-zinc-400">${row.install_date || row.date_created}</div></div><div class="col-span-2 text-zinc-600 truncate pr-2">${(row.product||'').split('(')[0]}</div><div class="col-span-2 text-right font-mono font-medium text-primary">₱${(parseFloat(row.price)||0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div><div class="col-span-2 text-center"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border ${badge}">${s}</span></div><div class="col-span-1 text-center opacity-0 group-hover:opacity-100 transition"><svg class="w-4 h-4 text-zinc-400 mx-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg></div></div>`;
            });
        }

        function renderPaginationControls(totalPages) {
            const container = document.getElementById('pagination-controls');
            container.innerHTML = '';

            if (totalPages <= 1) {
                container.innerHTML = `<span class="text-[10px] text-zinc-400">Showing all results</span>`;
                return;
            }

            const prevDisabled = CURRENT_PAGE === 1;
            const nextDisabled = CURRENT_PAGE === totalPages;

            const prevBtn = document.createElement('button');
            prevBtn.textContent = 'Prev';
            prevBtn.className = `px-2 py-1 border rounded-md text-[10px] font-bold uppercase ${prevDisabled ? 'text-zinc-300 border-zinc-200 cursor-not-allowed' : 'text-zinc-600 border-zinc-300 hover:text-primary hover:border-primary'}`;
            prevBtn.disabled = prevDisabled;
            prevBtn.addEventListener('click', () => {
                if (CURRENT_PAGE > 1) {
                    CURRENT_PAGE--;
                    renderTable();
                }
            });

            const nextBtn = document.createElement('button');
            nextBtn.textContent = 'Next';
            nextBtn.className = `px-2 py-1 border rounded-md text-[10px] font-bold uppercase ${nextDisabled ? 'text-zinc-300 border-zinc-200 cursor-not-allowed' : 'text-zinc-600 border-zinc-300 hover:text-primary hover:border-primary'}`;
            nextBtn.disabled = nextDisabled;
            nextBtn.addEventListener('click', () => {
                if (CURRENT_PAGE < totalPages) {
                    CURRENT_PAGE++;
                    renderTable();
                }
            });

            const pagesWrap = document.createElement('div');
            pagesWrap.className = 'flex items-center gap-1';

            for (let i = 1; i <= totalPages; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.textContent = String(i);
                const isActive = i === CURRENT_PAGE;
                pageBtn.className = `px-2 py-1 rounded-md text-[10px] font-bold ${isActive ? 'bg-primary text-white' : 'border border-zinc-300 text-zinc-600 hover:text-primary hover:border-primary'}`;
                pageBtn.disabled = isActive;
                pageBtn.addEventListener('click', () => {
                    CURRENT_PAGE = i;
                    renderTable();
                });
                pagesWrap.appendChild(pageBtn);
            }

            const left = document.createElement('div');
            left.className = 'flex items-center gap-2';
            left.appendChild(prevBtn);
            left.appendChild(pagesWrap);
            left.appendChild(nextBtn);

            container.appendChild(left);
        }

        function renderKanban() {
            const cols = { 'pending': document.getElementById('col-pending'), 'confirmed': document.getElementById('col-confirmed'), 'fabrication': document.getElementById('col-fabrication'), 'installation': document.getElementById('col-installation') };
            const counts = { 'pending':0, 'confirmed':0, 'fabrication':0, 'installation':0 };
            Object.values(cols).forEach(el => el.innerHTML = '');
            if (DATA_STATE === 'loading') {
                Object.values(cols).forEach(el => {
                    el.innerHTML = `<div class="space-y-2">${'<div class="h-16 rounded-lg bg-white border border-zinc-200 animate-pulse"></div>'.repeat(3)}</div>`;
                });
                Object.keys(counts).forEach(k => document.getElementById(`count-${k}`).textContent = '...');
                return;
            }
            if (DATA_STATE === 'error') {
                Object.values(cols).forEach(el => {
                    el.innerHTML = stateMessageHTML('Load failed', 'Unable to fetch workflow.', true, true);
                });
                Object.keys(counts).forEach(k => document.getElementById(`count-${k}`).textContent = '!');
                return;
            }
            GLOBAL_DATA.forEach(row => {
                const s = (row.status || 'Pending');
                if(['Void', 'Cancelled'].includes(s)) return;
                let targetCol = s.toLowerCase();
                if(s === 'Site Visit') targetCol = 'confirmed'; 
                if(!cols[targetCol]) targetCol = 'pending'; 
                if(cols[targetCol]) {
                    counts[targetCol]++;
                    const custName = row.customer_name_snapshot || row.customer || row.name;
                    cols[targetCol].innerHTML += `<div class="bg-white p-3 rounded-xl shadow-sm border border-zinc-200 cursor-pointer hover:shadow-md hover:border-primary/30 transition group" onclick="openModal('${row.id}')"><div class="flex justify-between mb-2"><span class="text-[10px] font-mono text-zinc-400 group-hover:text-primary transition">${row.id}</span><span class="text-[10px] font-bold">₱${(parseFloat(row.price)||0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span></div><div class="font-bold text-xs text-primary mb-1 truncate">${custName}</div><div class="text-[10px] text-zinc-500 truncate">${(row.product||'').split('(')[0]}</div></div>`;
                }
            });
            const totalCards = Object.values(counts).reduce((a, b) => a + b, 0);
            if (totalCards === 0) {
                cols.pending.innerHTML = stateMessageHTML('No active workflow yet', 'New bookings will appear here.');
            }
            Object.keys(counts).forEach(k => document.getElementById(`count-${k}`).textContent = counts[k]);
        }

        function normalizeAuditTimestamp(rawTs) {
            let ts = 0;
            if (typeof rawTs === 'number') ts = rawTs;
            else if (typeof rawTs === 'string') ts = Number(rawTs) || 0;
            if (ts > 0 && ts < 100000000000) ts = ts * 1000;
            return ts;
        }

        function bindAuditFilters() {
            const actionEl = document.getElementById('audit-filter-action');
            const actorEl = document.getElementById('audit-filter-actor');
            const fromEl = document.getElementById('audit-filter-from');
            const toEl = document.getElementById('audit-filter-to');
            const clearDatesEl = document.getElementById('audit-filter-clear-dates');

            if (actionEl) {
                actionEl.addEventListener('change', (e) => {
                    AUDIT_FILTER_ACTION = String(e.target.value || 'all').trim().toLowerCase();
                    generateAuditLog();
                });
            }
            if (actorEl) {
                actorEl.addEventListener('change', (e) => {
                    AUDIT_FILTER_ACTOR = String(e.target.value || 'all').trim().toLowerCase();
                    generateAuditLog();
                });
            }
            if (fromEl) {
                fromEl.addEventListener('change', (e) => {
                    AUDIT_FILTER_FROM = String(e.target.value || '').trim();
                    generateAuditLog();
                });
            }
            if (toEl) {
                toEl.addEventListener('change', (e) => {
                    AUDIT_FILTER_TO = String(e.target.value || '').trim();
                    generateAuditLog();
                });
            }
            if (clearDatesEl) {
                clearDatesEl.addEventListener('click', () => {
                    AUDIT_FILTER_FROM = '';
                    AUDIT_FILTER_TO = '';
                    if (fromEl) fromEl.value = '';
                    if (toEl) toEl.value = '';
                    generateAuditLog();
                });
            }
        }

        function generateAuditLog() {
            const widget = document.getElementById('audit-log-widget');
            widget.innerHTML = "";
            if (DATA_STATE === 'loading' || DATA_STATE === 'error') {
                renderOverviewWidgetsState();
                return;
            }
            const entries = [];
            const actorSet = new Set();
            const toTitle = (raw) => String(raw || '')
                .replace(/[_-]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .toLowerCase()
                .replace(/\b\w/g, (m) => m.toUpperCase());

            GLOBAL_DATA.forEach(row => {
                const custName = row.customer_name_snapshot || row.customer || row.name || 'Guest';
                const history = Array.isArray(row.history) ? row.history : [];

                history.forEach(h => {
                    const action = String(h.action || '').trim();
                    if (!action) return;
                    const ts = normalizeAuditTimestamp(h.timestamp);
                    const actor = String(h.actor || (action === 'created' ? 'customer' : 'system')).trim().toLowerCase();
                    if (actor) actorSet.add(actor);

                    entries.push({
                        ts: ts,
                        bookingId: row.id,
                        customer: custName,
                        oldStatus: h.old_status ?? h.from ?? '',
                        newStatus: h.new_status ?? h.to ?? '',
                        action: action,
                        actor: actor || 'system',
                        reason: String(h.reason || h.details || '').trim()
                    });
                });
            });

            const actorFilter = document.getElementById('audit-filter-actor');
            if (actorFilter) {
                const currentVal = actorFilter.value || 'all';
                const options = ['all', ...Array.from(actorSet).sort()];
                actorFilter.innerHTML = options
                    .map((actor) => {
                        const actorSafe = escapeHtml(actor);
                        const actorLabel = actor === 'all' ? 'All Actors' : escapeHtml(toTitle(actor));
                        return `<option value="${actorSafe}">${actorLabel}</option>`;
                    })
                    .join('');
                actorFilter.value = options.includes(currentVal) ? currentVal : 'all';
                AUDIT_FILTER_ACTOR = String(actorFilter.value || 'all').toLowerCase();
            }

            entries.sort((a, b) => (b.ts || 0) - (a.ts || 0));
            const filtered = entries.filter((entry) => {
                if (AUDIT_FILTER_ACTION !== 'all' && String(entry.action).toLowerCase() !== AUDIT_FILTER_ACTION) {
                    return false;
                }
                if (AUDIT_FILTER_ACTOR !== 'all' && String(entry.actor).toLowerCase() !== AUDIT_FILTER_ACTOR) {
                    return false;
                }
                if (AUDIT_FILTER_FROM) {
                    const fromTs = Date.parse(`${AUDIT_FILTER_FROM}T00:00:00`);
                    if (!Number.isNaN(fromTs) && entry.ts && entry.ts < fromTs) return false;
                }
                if (AUDIT_FILTER_TO) {
                    const toTs = Date.parse(`${AUDIT_FILTER_TO}T23:59:59`);
                    if (!Number.isNaN(toTs) && entry.ts && entry.ts > toTs) return false;
                }
                return true;
            });

            const display = filtered.slice(0, 8);
            if (display.length === 0) {
                widget.innerHTML = stateMessageHTML('No Audit Entries', 'Try changing filter values.', false, true);
                return;
            }
            display.forEach(entry => {
                const when = entry.ts ? new Date(entry.ts).toLocaleString() : 'Recent';
                const statusText = entry.oldStatus && entry.newStatus
                    ? `${entry.oldStatus} → ${entry.newStatus}`
                    : (entry.newStatus || entry.oldStatus || '');
                const actorText = escapeHtml(toTitle(entry.actor || 'system'));
                const customerText = escapeHtml(entry.customer || 'Guest');
                const refText = escapeHtml(entry.bookingId || '—');
                const transitionText = escapeHtml(statusText || '');
                const normalizedAction = String(entry.action || '').trim().toLowerCase();
                const formalActionLabelMap = {
                    created: 'Created Booking',
                    status_change: 'Updated Booking Status',
                    schedule_change: 'Updated Schedule Date',
                    auto_void: 'Auto-Voided Quotation',
                    retention_purge: 'Purged Customer Data',
                    customer_cancel: 'Cancelled Booking'
                };
                const formalActionText = escapeHtml(formalActionLabelMap[normalizedAction] || toTitle(normalizedAction || 'status_change'));
                let headlineText = `<span class="font-bold">${actorText}</span> updated booking for <span class="font-bold">${customerText}</span> <span class="text-zinc-500">(Ref: ${refText})</span>`;
                if (normalizedAction === 'created') {
                    headlineText = `<span class="font-bold">${actorText}</span> created booking for <span class="font-bold">${customerText}</span> <span class="text-zinc-500">(Ref: ${refText})</span>`;
                } else if (normalizedAction === 'auto_void') {
                    headlineText = `<span class="font-bold">${actorText}</span> auto-voided quotation for <span class="font-bold">${customerText}</span> <span class="text-zinc-500">(Ref: ${refText})</span>`;
                } else if (normalizedAction === 'retention_purge') {
                    headlineText = `<span class="font-bold">${actorText}</span> purged customer data for <span class="font-bold">${customerText}</span> <span class="text-zinc-500">(Ref: ${refText})</span>`;
                }
                const detailParts = [
                    `Action: ${formalActionText}`,
                    `Target: Booking ${refText}`
                ];
                if (transitionText) detailParts.push(`Before → After: ${transitionText}`);
                const reasonText = entry.reason ? `<div class="text-[10px] text-zinc-500 mt-0.5">Reason: ${escapeHtml(entry.reason)}</div>` : '';
                widget.innerHTML += `<div class="relative">
                    <div class="absolute -left-[22px] top-1.5 h-2 w-2 rounded-full bg-zinc-300 border border-white ring-2 ring-zinc-50"></div>
                    <div class="text-[10px] text-zinc-400 uppercase tracking-widest mb-0.5">${when}</div>
                    <p class="text-xs text-primary leading-tight">${headlineText}</p>
                    <div class="text-[10px] text-zinc-500">${detailParts.join(' • ')}</div>
                    ${reasonText}
                </div>`;
            });
        }

        function renderCustomerDB() {
            const list = document.getElementById('customer-list');
            const countEl = document.getElementById('customer-count');
            const paginationEl = document.getElementById('customer-pagination');
            if (!list || !paginationEl) return;
            if (DATA_STATE === 'loading') {
                if (countEl) countEl.textContent = 'Loading customers...';
                list.innerHTML = `<div class="px-6 py-5 space-y-3">${'<div class="h-10 rounded-lg bg-zinc-100 animate-pulse"></div>'.repeat(6)}</div>`;
                paginationEl.innerHTML = `<span class="text-[10px] text-zinc-400">Please wait...</span>`;
                return;
            }
            if (DATA_STATE === 'error') {
                if (countEl) countEl.textContent = 'Customers unavailable';
                list.innerHTML = stateMessageHTML('Unable to load customers', DATA_ERROR || 'Please retry.', true);
                paginationEl.innerHTML = `<span class="text-[10px] text-red-500">Retry required</span>`;
                return;
            }

            const getTs = (row) => {
                const raw = row.created_at || row.date_created || row.install_date || row.date;
                if (!raw) return 0;
                if (typeof raw === 'number' || (typeof raw === 'string' && /^[0-9]+$/.test(raw))) {
                    const num = Number(raw);
                    if (!Number.isFinite(num)) return 0;
                    return num > 100000000000 ? num : num * 1000;
                }
                const ts = Date.parse(raw);
                return Number.isNaN(ts) ? 0 : ts;
            };

            const byCustomer = {};
            GLOBAL_DATA.forEach((row) => {
                const name = String(row.customer_name_snapshot || row.customer || row.name || 'Guest').trim();
                const key = name.toLowerCase();
                if (!byCustomer[key]) {
                    byCustomer[key] = {
                        name,
                        bookings: 0,
                        totalValue: 0,
                        lastActivityTs: 0,
                        lastActivityRaw: '',
                        lastStatus: 'Pending',
                        records: []
                    };
                }

                const item = byCustomer[key];
                item.bookings += 1;
                item.totalValue += parseFloat(row.price) || 0;

                const ts = getTs(row);
                if (ts >= item.lastActivityTs) {
                    item.lastActivityTs = ts;
                    item.lastActivityRaw = row.install_date || row.created_at || row.date_created || '';
                    item.lastStatus = row.status || 'Pending';
                }
                item.records.push(row);
            });

            let customers = Object.values(byCustomer).sort((a, b) => b.totalValue - a.totalValue);
            if (CUSTOMER_SEARCH) {
                customers = customers.filter((c) => {
                    const hay = `${c.name}`.toLowerCase();
                    return hay.includes(CUSTOMER_SEARCH);
                });
            }

            if (countEl) countEl.textContent = `${customers.length} customers`;
            list.innerHTML = '';
            CUSTOMER_INDEX = {};

            if (customers.length === 0) {
                list.innerHTML = `<div class="px-6 py-8 text-xs text-zinc-500">No customers found.</div>`;
                paginationEl.innerHTML = `<span class="text-[10px] text-zinc-400">No pages</span>`;
                return;
            }

            const totalPages = Math.max(1, Math.ceil(customers.length / CUSTOMER_ROWS_PER_PAGE));
            if (CUSTOMER_PAGE > totalPages) CUSTOMER_PAGE = totalPages;
            const start = (CUSTOMER_PAGE - 1) * CUSTOMER_ROWS_PER_PAGE;
            const pageRows = customers.slice(start, start + CUSTOMER_ROWS_PER_PAGE);
            renderCustomerPagination(totalPages, customers.length);

            pageRows.forEach((c, idx) => {
                const globalIdx = start + idx;

                const lastDate = c.lastActivityTs ? new Date(c.lastActivityTs).toLocaleDateString() : (c.lastActivityRaw || '—');
                const customerKey = `cust_${globalIdx}`;
                CUSTOMER_INDEX[customerKey] = c;

                list.innerHTML += `<div class="grid grid-cols-12 px-6 py-3 items-center text-xs hover:bg-zinc-50 transition cursor-pointer" onclick="openCustomerModal('${customerKey}')">
                    <div class="col-span-6">
                        <div class="font-bold text-primary truncate">${c.name}</div>
                    </div>
                    <div class="col-span-2 text-zinc-600">${lastDate}</div>
                    <div class="col-span-1 text-right text-primary" style="font-family: Inter, sans-serif;">${c.bookings}</div>
                    <div class="col-span-3 text-right font-medium text-primary" style="font-family: Inter, sans-serif;">₱${c.totalValue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                </div>`;
            });
        }

        function renderCustomerPagination(totalPages, totalRecords) {
            const container = document.getElementById('customer-pagination');
            if (!container) return;
            container.innerHTML = '';

            const makeRetentionButton = () => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = 'Run Data Retention';
                btn.className = 'px-2.5 py-1.5 text-[10px] font-semibold rounded-lg border border-zinc-300 bg-white text-zinc-700 hover:border-primary hover:text-primary transition whitespace-nowrap';
                btn.title = 'Run customer data retention cleanup (12 months)';
                btn.addEventListener('click', runRetentionPurge);
                return btn;
            };

            if (totalPages <= 1) {
                const leftSimple = document.createElement('span');
                leftSimple.className = 'text-[10px] text-zinc-400';
                leftSimple.textContent = 'Page 1 of 1';
                const rightSimple = document.createElement('div');
                rightSimple.className = 'flex items-center';
                rightSimple.appendChild(makeRetentionButton());
                container.appendChild(leftSimple);
                container.appendChild(rightSimple);
                return;
            }

            const prevDisabled = CUSTOMER_PAGE === 1;
            const nextDisabled = CUSTOMER_PAGE === totalPages;

            const prevBtn = document.createElement('button');
            prevBtn.textContent = 'Prev';
            prevBtn.className = `px-2 py-1 border rounded-md text-[10px] font-bold uppercase ${prevDisabled ? 'text-zinc-300 border-zinc-200 cursor-not-allowed' : 'text-zinc-600 border-zinc-300 hover:text-primary hover:border-primary'}`;
            prevBtn.disabled = prevDisabled;
            prevBtn.addEventListener('click', () => {
                if (CUSTOMER_PAGE > 1) {
                    CUSTOMER_PAGE--;
                    renderCustomerDB();
                }
            });

            const nextBtn = document.createElement('button');
            nextBtn.textContent = 'Next';
            nextBtn.className = `px-2 py-1 border rounded-md text-[10px] font-bold uppercase ${nextDisabled ? 'text-zinc-300 border-zinc-200 cursor-not-allowed' : 'text-zinc-600 border-zinc-300 hover:text-primary hover:border-primary'}`;
            nextBtn.disabled = nextDisabled;
            nextBtn.addEventListener('click', () => {
                if (CUSTOMER_PAGE < totalPages) {
                    CUSTOMER_PAGE++;
                    renderCustomerDB();
                }
            });

            const pagesWrap = document.createElement('div');
            pagesWrap.className = 'flex items-center gap-1';
            for (let i = 1; i <= totalPages; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.textContent = String(i);
                const isActive = i === CUSTOMER_PAGE;
                pageBtn.className = `px-2 py-1 rounded-md text-[10px] font-bold ${isActive ? 'bg-primary text-white' : 'border border-zinc-300 text-zinc-600 hover:text-primary hover:border-primary'}`;
                pageBtn.disabled = isActive;
                pageBtn.addEventListener('click', () => {
                    CUSTOMER_PAGE = i;
                    renderCustomerDB();
                });
                pagesWrap.appendChild(pageBtn);
            }

            const left = document.createElement('div');
            left.className = 'flex items-center gap-2';
            left.appendChild(prevBtn);
            left.appendChild(pagesWrap);

            const right = document.createElement('span');
            right.className = 'flex items-center gap-2';
            const rightInfo = document.createElement('span');
            rightInfo.className = 'text-[10px] text-zinc-400';
            rightInfo.textContent = `Page ${CUSTOMER_PAGE} of ${totalPages}`;
            right.appendChild(rightInfo);
            right.appendChild(makeRetentionButton());

            container.appendChild(left);
            container.appendChild(right);
        }

        async function fetchProductData() {
            const list = document.getElementById('product-list');
            const pagination = document.getElementById('product-pagination');
            if (list) {
                list.innerHTML = `<div class="px-6 py-5 space-y-3">${'<div class="h-10 rounded-lg bg-zinc-100 animate-pulse"></div>'.repeat(5)}</div>`;
            }
            if (pagination) {
                pagination.innerHTML = `<span class="text-[10px] text-zinc-400">Loading products...</span>`;
            }

            try {
                const { response, json } = await fetchJsonWithTimeout('backend/products_admin.php?action=list', {
                    headers: { 'X-CSRF-Token': CSRF_TOKEN }
                }, 12000);
                if (!json || json.status !== 'success' || !Array.isArray(json.data)) {
                    throw new Error(json?.message || 'Failed to load products');
                }
                PRODUCT_DATA = json.data;
                renderProductTable();
            } catch (e) {
                if (list) {
                    list.innerHTML = stateMessageHTML('Unable to load products', String(e.message || 'Please retry.'), true);
                }
                if (pagination) {
                    pagination.innerHTML = `<span class="text-[10px] text-red-500">Retry required</span>`;
                }
            }
        }

        function inferProductType(row) {
            const text = `${row.product_name || ''} ${row.variant_label || ''}`.toLowerCase();
            if (/window|casement|awning|jalousie/.test(text)) return 'windows';
            if (/door|french/.test(text)) return 'doors';
            if (/sliding/.test(text)) return 'sliding';
            if (/partition/.test(text)) return 'partitions';
            if (/railing|balustrade/.test(text)) return 'railings';
            if (/accessor|patch fitting|handle|fitting/.test(text)) return 'accessories';
            return 'others';
        }

        function resolveProductType(row) {
            const direct = String(row.product_type || '').trim().toLowerCase();
            if (['windows', 'doors', 'sliding', 'partitions', 'railings', 'accessories', 'others'].includes(direct)) {
                return direct;
            }
            return inferProductType(row);
        }

        function getFilteredProductRows() {
            let rows = Array.isArray(PRODUCT_DATA) ? [...PRODUCT_DATA] : [];
            if (PRODUCT_SEARCH) {
                rows = rows.filter((row) => {
                    const hay = `${row.product_name || ''} ${row.variant_label || ''} ${row.product_key || ''}`.toLowerCase();
                    return hay.includes(PRODUCT_SEARCH);
                });
            }
            if (PRODUCT_TYPE_FILTER !== 'all') {
                rows = rows.filter((row) => resolveProductType(row) === PRODUCT_TYPE_FILTER);
            }
            return rows;
        }

        function refreshProductSelectionState() {
            const selectedEl = document.getElementById('product-selected-count');
            if (selectedEl) {
                selectedEl.textContent = `${SELECTED_PRODUCT_KEYS.size} selected`;
            }
            updateMobileSelectedActionFab();
        }

        function toggleProductSelection(itemKey, checked) {
            const key = String(itemKey || '').trim();
            if (!key) return;
            if (checked) SELECTED_PRODUCT_KEYS.add(key);
            else SELECTED_PRODUCT_KEYS.delete(key);
            refreshProductSelectionState();
            renderProductTable();
        }

        function toggleProductSelectionByCard(itemKey) {
            const key = String(itemKey || '').trim();
            if (!key) return;
            const nextChecked = !SELECTED_PRODUCT_KEYS.has(key);
            toggleProductSelection(key, nextChecked);
        }

        function handleProductCardKeyToggle(event, itemKey) {
            if (!event) return;
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggleProductSelectionByCard(itemKey);
            }
        }

        function clearProductSelection() {
            SELECTED_PRODUCT_KEYS = new Set();
            refreshProductSelectionState();
            renderProductTable();
        }

        function updateMobileSelectedActionFab() {
            const fab = document.getElementById('mobile-selected-actions-fab');
            if (!fab) return;
            const isMobile = window.innerWidth < 768;
            const productsViewActive = !!document.querySelector('#main-nav-mobile [data-view="products"].active, #main-nav [data-view="products"].active');
            const show = isMobile && productsViewActive && SELECTED_PRODUCT_KEYS.size > 0;
            fab.classList.toggle('hidden', !show);
        }

        async function openMobileSelectedActions() {
            if (SELECTED_PRODUCT_KEYS.size <= 0) return;
            const selectedCount = SELECTED_PRODUCT_KEYS.size;
            await Swal.fire({
                title: 'Selected Actions',
                html: `
                    <div class="text-left">
                        <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-[11px] font-semibold text-zinc-600">
                            <span class="inline-block h-2 w-2 rounded-full bg-zinc-900"></span>
                            ${selectedCount} selected
                        </div>
                        <div class="grid grid-cols-1 gap-2">
                            <button id="mobile-act-move" class="w-full h-11 rounded-lg border border-zinc-300 bg-white text-zinc-700 text-xs font-semibold hover:border-primary hover:text-primary transition inline-flex items-center justify-center gap-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12h18"></path><path d="M12 3l9 9-9 9"></path></svg>
                                <span>Move Selected</span>
                            </button>
                            <button id="mobile-act-edit" class="w-full h-11 rounded-lg border border-zinc-300 bg-white text-zinc-700 text-xs font-semibold hover:border-primary hover:text-primary transition inline-flex items-center justify-center gap-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path></svg>
                                <span>Edit</span>
                            </button>
                            <button id="mobile-act-delete" class="w-full h-11 rounded-lg border border-red-200 bg-red-50 text-red-600 text-xs font-semibold hover:bg-red-100 transition inline-flex items-center justify-center gap-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path><path d="M10 11v6M14 11v6"></path></svg>
                                <span>Delete</span>
                            </button>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                showConfirmButton: false,
                cancelButtonText: 'Close',
                buttonsStyling: false,
                customClass: {
                    popup: 'rounded-xl border border-zinc-200 shadow-2xl p-0',
                    title: 'text-left text-lg font-semibold text-zinc-900 px-6 pt-6 pb-0',
                    htmlContainer: 'px-6 pt-3 pb-1',
                    actions: 'w-full px-6 pb-6 pt-4 justify-end',
                    cancelButton: 'h-9 px-4 rounded-md border border-zinc-300 bg-white text-zinc-700 text-xs font-semibold hover:bg-zinc-50 transition'
                },
                didOpen: () => {
                    const moveBtn = document.getElementById('mobile-act-move');
                    const editBtn = document.getElementById('mobile-act-edit');
                    const delBtn = document.getElementById('mobile-act-delete');
                    if (moveBtn) moveBtn.addEventListener('click', async () => { Swal.close(); await bulkMoveSelectedProductTypes(); });
                    if (editBtn) editBtn.addEventListener('click', () => { Swal.close(); editSelectedProductVariant(); });
                    if (delBtn) delBtn.addEventListener('click', async () => { Swal.close(); await deleteSelectedProductVariants(); });
                }
            });
        }

        async function openMobileStatusLegend() {
            if (window.innerWidth >= 768) return;
            await Swal.fire({
                title: 'Status Color Note',
                html: `
                    <div class="space-y-2 text-left text-xs">
                        <div class="flex items-center gap-2"><span class="inline-block h-2.5 w-2.5 rounded-full bg-zinc-400"></span><span>Pending</span></div>
                        <div class="flex items-center gap-2"><span class="inline-block h-2.5 w-2.5 rounded-full bg-cyan-500"></span><span>Site Visit</span></div>
                        <div class="flex items-center gap-2"><span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-500"></span><span>Confirmed</span></div>
                        <div class="flex items-center gap-2"><span class="inline-block h-2.5 w-2.5 rounded-full bg-orange-500"></span><span>Fabrication</span></div>
                        <div class="flex items-center gap-2"><span class="inline-block h-2.5 w-2.5 rounded-full bg-purple-500"></span><span>Installation</span></div>
                        <div class="flex items-center gap-2"><span class="inline-block h-2.5 w-2.5 rounded-full bg-zinc-700"></span><span>Completed</span></div>
                        <div class="flex items-center gap-2"><span class="inline-block h-2.5 w-2.5 rounded-full bg-red-500"></span><span>Cancelled / Void</span></div>
                    </div>
                `,
                showConfirmButton: false,
                showCancelButton: true,
                cancelButtonText: 'Close',
                buttonsStyling: false,
                customClass: {
                    popup: 'rounded-xl border border-zinc-200 shadow-2xl p-0',
                    title: 'text-left text-lg font-semibold text-zinc-900 px-6 pt-6 pb-0',
                    htmlContainer: 'px-6 pt-3 pb-0',
                    actions: 'w-full px-6 pb-6 pt-4 justify-end',
                    cancelButton: 'h-9 px-4 rounded-md border border-zinc-300 bg-white text-zinc-700 text-xs font-semibold hover:bg-zinc-50 transition'
                }
            });
        }

        async function bulkUpdateProductType(itemKeys, targetType) {
            const keys = Array.isArray(itemKeys) ? itemKeys.filter(Boolean) : [];
            if (keys.length === 0) {
                showCustomAlert('error', 'No Selection', 'Select at least one variant to move.');
                return;
            }
            const validTypes = ['windows', 'doors', 'sliding', 'partitions', 'railings', 'accessories', 'others'];
            if (!validTypes.includes(targetType)) {
                showCustomAlert('error', 'Invalid Type', 'Please select a valid target type.');
                return;
            }

            try {
                const form = new FormData();
                form.append('target_type', targetType);
                form.append('variant_keys_json', JSON.stringify(keys));

                const req = await fetch('backend/products_admin.php?action=bulk_set_type', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': CSRF_TOKEN },
                    body: form
                });
                const res = await req.json();
                if (!res || res.status !== 'success') {
                    throw new Error(res?.message || 'Bulk update failed.');
                }

                showCustomAlert('success', 'Bulk Move Complete', `Updated: ${res.updated || 0}, Skipped: ${res.skipped || 0}`);
                await fetchProductData();
                clearProductSelection();
            } catch (err) {
                showCustomAlert('error', 'Bulk Move Failed', String(err.message || 'Unable to move selected variants.'));
            }
        }

        async function bulkMoveSelectedProductTypes() {
            const selected = Array.from(SELECTED_PRODUCT_KEYS);
            if (selected.length === 0) {
                showCustomAlert('error', 'No Selection', 'Select at least one variant first.');
                return;
            }
            const options = ['windows', 'doors', 'sliding', 'partitions', 'railings', 'accessories', 'others'];
            const htmlOptions = options.map((t) => `<option value="${t}">${t.charAt(0).toUpperCase() + t.slice(1)}</option>`).join('');
            const prompt = await Swal.fire({
                title: 'Move selected variants',
                html: `
                    <div class="text-left">
                        <p class="text-sm text-zinc-600 mb-3">Choose target type for ${selected.length} selected variant(s).</p>
                        <select id="bulk-target-type" class="w-full h-10 border border-zinc-300 rounded-md px-3 text-sm bg-white outline-none focus:border-zinc-900 transition">
                            ${htmlOptions}
                        </select>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Move',
                cancelButtonText: 'Cancel',
                preConfirm: () => String(document.getElementById('bulk-target-type')?.value || '').trim().toLowerCase(),
                buttonsStyling: false,
                customClass: {
                    popup: 'rounded-xl border border-zinc-200 shadow-2xl p-0',
                    title: 'text-left text-lg font-semibold text-zinc-900 px-6 pt-6 pb-0',
                    htmlContainer: 'px-6 pt-3 pb-0',
                    actions: 'w-full px-6 pb-6 pt-4 gap-2 justify-end',
                    confirmButton: 'h-9 px-4 rounded-md bg-zinc-900 text-white text-xs font-semibold hover:bg-zinc-800 transition',
                    cancelButton: 'h-9 px-4 rounded-md border border-zinc-300 bg-white text-zinc-700 text-xs font-semibold hover:bg-zinc-50 transition'
                }
            });
            if (!prompt.isConfirmed) return;
            await bulkUpdateProductType(selected, String(prompt.value || '').trim().toLowerCase());
        }

        async function bulkMoveFilteredProductTypes() {
            const rows = getFilteredProductRows();
            const keys = rows.map((row) => `${row.product_key}__${row.variant_key}`);
            if (keys.length === 0) {
                showCustomAlert('error', 'No Filtered Records', 'No variants found for the current filter.');
                return;
            }
            await bulkUpdateProductType(keys, 'others');
        }

        function editSelectedProductVariant() {
            const selected = Array.from(SELECTED_PRODUCT_KEYS);
            if (selected.length === 0) {
                showCustomAlert('error', 'No Selection', 'Select one variant first.');
                return;
            }
            if (selected.length > 1) {
                showCustomAlert('error', 'Single Selection Required', 'Edit works for one selected variant only.');
                return;
            }
            openProductModal(selected[0]);
        }

        async function deleteSelectedProductVariants() {
            const selected = Array.from(SELECTED_PRODUCT_KEYS);
            if (selected.length === 0) {
                showCustomAlert('error', 'No Selection', 'Select at least one variant first.');
                return;
            }
            const confirmed = await openProductDeleteSelectedModal(selected.length);
            if (!confirmed) return;

            let successCount = 0;
            let failCount = 0;

            for (const itemKey of selected) {
                const [productKey, variantKey] = String(itemKey || '').split('__');
                const item = PRODUCT_DATA.find((row) => row.product_key === productKey && row.variant_key === variantKey);
                if (!productKey || !variantKey || !item) {
                    failCount++;
                    continue;
                }
                try {
                    const form = new FormData();
                    form.append('product_key', productKey);
                    form.append('variant_key', variantKey);
                    form.append('product_name', item.product_name || item.product_key);
                    form.append('variant_label', item.variant_label || item.variant_key);

                    const req = await fetch('backend/products_admin.php?action=delete', {
                        method: 'POST',
                        headers: { 'X-CSRF-Token': CSRF_TOKEN },
                        body: form
                    });
                    const res = await req.json();
                    if (!res || res.status !== 'success') {
                        failCount++;
                        continue;
                    }
                    successCount++;
                } catch (_) {
                    failCount++;
                }
            }

            await fetchProductData();
            clearProductSelection();
            showCustomAlert('success', 'Delete Complete', `Deleted: ${successCount}, Failed: ${failCount}`);
        }

        function openProductDeleteSelectedModal(selectedCount) {
            const modal = document.getElementById('product-delete-selected-modal');
            const countEl = document.getElementById('pdsm-count');
            if (!modal || !countEl) {
                return Promise.resolve(false);
            }
            countEl.textContent = String(Math.max(0, Number(selectedCount) || 0));
            modal.classList.add('open');
            return new Promise((resolve) => {
                PRODUCT_DELETE_SELECTED_RESOLVER = resolve;
            });
        }

        function resolveProductDeleteSelectedModal(confirmed) {
            const modal = document.getElementById('product-delete-selected-modal');
            if (modal) {
                modal.classList.remove('open');
            }
            if (typeof PRODUCT_DELETE_SELECTED_RESOLVER === 'function') {
                const done = PRODUCT_DELETE_SELECTED_RESOLVER;
                PRODUCT_DELETE_SELECTED_RESOLVER = null;
                done(Boolean(confirmed));
            }
        }

        function handleProductDeleteSelectedBackdrop(evt) {
            if (evt && evt.target === evt.currentTarget) {
                resolveProductDeleteSelectedModal(false);
            }
        }

        function renderProductTable() {
            const list = document.getElementById('product-list');
            const pagination = document.getElementById('product-pagination');
            if (!list || !pagination) return;
            const rows = getFilteredProductRows();

            if (rows.length === 0) {
                list.innerHTML = `<div class="px-6 py-8 text-xs text-zinc-500">No product variants found.</div>`;
                pagination.innerHTML = `<span class="text-[10px] text-zinc-400">No pages</span>`;
                refreshProductSelectionState();
                return;
            }

            const totalPages = Math.max(1, Math.ceil(rows.length / PRODUCT_ROWS_PER_PAGE));
            if (PRODUCT_PAGE > totalPages) PRODUCT_PAGE = totalPages;
            const start = (PRODUCT_PAGE - 1) * PRODUCT_ROWS_PER_PAGE;
            const pageRows = rows.slice(start, start + PRODUCT_ROWS_PER_PAGE);

            list.innerHTML = '';
            pageRows.forEach((row) => {
                const noScreen = Number(row.price_no_screen || 0);
                const active = row.is_available === true;
                const itemKey = `${row.product_key}__${row.variant_key}`;
                const isSelected = SELECTED_PRODUCT_KEYS.has(itemKey);
                const type = resolveProductType(row);
                const typeLabel = type.charAt(0).toUpperCase() + type.slice(1);
                const priceText = `₱${noScreen.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                list.innerHTML += `<div class="px-4 md:px-6 py-4 md:py-3 hover:bg-zinc-50 transition ${isSelected ? 'bg-zinc-50' : ''}">
                    <div class="md:hidden">
                        <div
                            class="rounded-xl border ${isSelected ? 'border-primary ring-1 ring-primary/30' : 'border-zinc-200'} bg-white p-3 space-y-3 cursor-pointer"
                            role="button"
                            tabindex="0"
                            onclick="toggleProductSelectionByCard('${escapeHtml(itemKey)}')"
                            onkeydown="handleProductCardKeyToggle(event, '${escapeHtml(itemKey)}')"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-primary leading-snug break-words">${escapeHtml(row.product_name || row.product_key)}</div>
                                    <div class="text-[10px] text-zinc-400 font-mono break-all">${escapeHtml(row.product_key || '')}</div>
                                </div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border shrink-0 bg-zinc-50 text-zinc-600 border-zinc-200">${escapeHtml(typeLabel)}</span>
                            </div>
                            <div class="min-w-0">
                                <div class="text-xs text-zinc-700 leading-snug break-words">${escapeHtml(row.variant_label || row.variant_key)}</div>
                                <div class="text-[10px] text-zinc-400 font-mono break-all">${escapeHtml(row.variant_key || '')}</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <p class="text-[10px] uppercase tracking-wide text-zinc-400">Base Price</p>
                                    <p class="font-mono font-semibold text-primary text-sm">${priceText}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] uppercase tracking-wide text-zinc-400">Active</p>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border ${active ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'bg-zinc-100 text-zinc-500 border-zinc-200'}">
                                        ${active ? 'Yes' : 'No'}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="hidden md:grid md:grid-cols-12 items-center text-xs">
                        <div class="col-span-1 text-center">
                            <label class="inline-flex items-center justify-center h-7 w-7 rounded-md">
                                <input type="checkbox" ${isSelected ? 'checked' : ''} onchange="toggleProductSelection('${escapeHtml(itemKey)}', this.checked)" class="grid-checkbox">
                            </label>
                        </div>
                        <div class="col-span-2">
                            <div class="font-semibold text-primary truncate">${escapeHtml(row.product_name || row.product_key)}</div>
                            <div class="text-[10px] text-zinc-400 font-mono">${escapeHtml(row.product_key || '')}</div>
                        </div>
                        <div class="col-span-4">
                            <div class="text-zinc-700 truncate">${escapeHtml(row.variant_label || row.variant_key)}</div>
                            <div class="text-[10px] text-zinc-400 font-mono">${escapeHtml(row.variant_key || '')}</div>
                        </div>
                        <div class="col-span-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border bg-zinc-50 text-zinc-600 border-zinc-200">${escapeHtml(typeLabel)}</span>
                        </div>
                        <div class="col-span-2 text-right font-mono text-primary">${priceText}</div>
                        <div class="col-span-1 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border ${active ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'bg-zinc-100 text-zinc-500 border-zinc-200'}">
                                ${active ? 'Yes' : 'No'}
                            </span>
                        </div>
                    </div>
                </div>`;
            });

            refreshProductSelectionState();
            renderProductPagination(totalPages, rows.length);
        }

        function renderProductPagination(totalPages, totalRecords) {
            const container = document.getElementById('product-pagination');
            if (!container) return;
            container.className = 'px-4 md:px-6 py-3 border-t border-border bg-white flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs';

            const prevDisabled = PRODUCT_PAGE === 1;
            const nextDisabled = PRODUCT_PAGE === totalPages;

            const prevBtn = document.createElement('button');
            prevBtn.textContent = 'Prev';
            prevBtn.className = `px-2 py-1 border rounded-md text-[10px] font-bold uppercase ${prevDisabled ? 'text-zinc-300 border-zinc-200 cursor-not-allowed' : 'text-zinc-600 border-zinc-300 hover:text-primary hover:border-primary'}`;
            prevBtn.disabled = prevDisabled;
            prevBtn.addEventListener('click', () => {
                if (PRODUCT_PAGE > 1) {
                    PRODUCT_PAGE--;
                    renderProductTable();
                }
            });

            const nextBtn = document.createElement('button');
            nextBtn.textContent = 'Next';
            nextBtn.className = `px-2 py-1 border rounded-md text-[10px] font-bold uppercase ${nextDisabled ? 'text-zinc-300 border-zinc-200 cursor-not-allowed' : 'text-zinc-600 border-zinc-300 hover:text-primary hover:border-primary'}`;
            nextBtn.disabled = nextDisabled;
            nextBtn.addEventListener('click', () => {
                if (PRODUCT_PAGE < totalPages) {
                    PRODUCT_PAGE++;
                    renderProductTable();
                }
            });

            const pagesWrap = document.createElement('div');
            pagesWrap.className = 'flex items-center gap-1';

            const maxVisible = 3;
            let startPage = Math.max(1, PRODUCT_PAGE - 1);
            let endPage = Math.min(totalPages, startPage + maxVisible - 1);
            startPage = Math.max(1, endPage - maxVisible + 1);

            for (let i = startPage; i <= endPage; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.textContent = String(i);
                const isActive = i === PRODUCT_PAGE;
                pageBtn.className = `px-2 py-1 rounded-md text-[10px] font-bold ${isActive ? 'bg-primary text-white' : 'border border-zinc-300 text-zinc-600 hover:text-primary hover:border-primary'}`;
                pageBtn.disabled = isActive;
                pageBtn.addEventListener('click', () => {
                    PRODUCT_PAGE = i;
                    renderProductTable();
                });
                pagesWrap.appendChild(pageBtn);
            }

            container.innerHTML = '';
            const left = document.createElement('div');
            left.className = 'flex items-center gap-2';
            left.appendChild(prevBtn);
            left.appendChild(pagesWrap);
            left.appendChild(nextBtn);

            const right = document.createElement('span');
            right.className = 'text-[10px] text-zinc-400';
            right.textContent = `${totalRecords} variants • Page ${PRODUCT_PAGE} of ${totalPages}`;

            const center = document.createElement('div');
            center.className = 'flex items-center justify-center gap-2 flex-wrap';
            if (SELECTED_PRODUCT_KEYS.size > 0 && window.innerWidth >= 768) {
                const moveBtn = document.createElement('button');
                moveBtn.textContent = 'Move Selected';
                moveBtn.className = 'px-3 py-1.5 border border-zinc-300 rounded-md text-[10px] font-semibold text-zinc-700 hover:text-primary hover:border-primary transition';
                moveBtn.addEventListener('click', bulkMoveSelectedProductTypes);

                const editBtn = document.createElement('button');
                editBtn.innerHTML = `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path></svg><span>Edit</span>`;
                editBtn.className = 'px-3 py-1.5 border border-zinc-300 rounded-md text-[10px] font-semibold text-zinc-700 hover:text-primary hover:border-primary transition inline-flex items-center gap-1.5';
                editBtn.addEventListener('click', editSelectedProductVariant);

                const deleteBtn = document.createElement('button');
                deleteBtn.innerHTML = `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path><path d="M10 11v6M14 11v6"></path></svg><span>Delete</span>`;
                deleteBtn.className = 'px-3 py-1.5 border border-red-200 rounded-md text-[10px] font-semibold text-red-600 hover:bg-red-50 transition inline-flex items-center gap-1.5';
                deleteBtn.addEventListener('click', deleteSelectedProductVariants);

                center.appendChild(moveBtn);
                center.appendChild(editBtn);
                center.appendChild(deleteBtn);
            }

            container.appendChild(left);
            if (center.childNodes.length > 0) {
                container.appendChild(center);
            }
            container.appendChild(right);
        }

        function openProductModal(itemKey) {
            const [productKey, variantKey] = String(itemKey || '').split('__');
            if (!productKey || !variantKey) return;
            const item = PRODUCT_DATA.find((row) => row.product_key === productKey && row.variant_key === variantKey);
            if (!item) return;

            ACTIVE_PRODUCT_ITEM = item;
            document.getElementById('pm-title').textContent = item.product_name || item.product_key;
            document.getElementById('pm-subtitle').textContent = item.variant_label || item.variant_key;
            document.getElementById('pm-price-no-screen').value = Number(item.price_no_screen || 0).toFixed(2);
            const currentType = String(item.product_type || '').trim().toLowerCase();
            document.getElementById('pm-product-type').value =
                ['windows', 'doors', 'sliding', 'partitions', 'railings', 'accessories', 'others'].includes(currentType)
                    ? currentType
                    : 'others';
            document.getElementById('pm-is-available').checked = item.is_available === true;
            document.getElementById('product-modal').classList.add('open');
        }

        function openProductCreateModal() {
            const nameEl = document.getElementById('pc-product-name');
            const variantEl = document.getElementById('pc-variant-label');
            const priceEl = document.getElementById('pc-price-no-screen');
            const typeEl = document.getElementById('pc-product-type');
            const activeEl = document.getElementById('pc-is-available');
            if (nameEl) nameEl.value = '';
            if (variantEl) variantEl.value = '';
            if (priceEl) priceEl.value = '';
            if (typeEl) typeEl.value = 'others';
            if (activeEl) activeEl.checked = true;
            document.getElementById('product-create-modal').classList.add('open');
        }

        async function saveProductPrice() {
            if (!ACTIVE_PRODUCT_ITEM) return;
            const priceNoScreen = parseFloat(document.getElementById('pm-price-no-screen').value);
            const productType = String(document.getElementById('pm-product-type').value || 'others').trim().toLowerCase();
            const isAvailable = document.getElementById('pm-is-available').checked;

            if (!Number.isFinite(priceNoScreen) || priceNoScreen <= 0) {
                showCustomAlert('error', 'Invalid Price', 'Base price must be greater than 0.');
                return;
            }
            if (!['windows', 'doors', 'sliding', 'partitions', 'railings', 'accessories', 'others'].includes(productType)) {
                showCustomAlert('error', 'Invalid Type', 'Please select a valid product type.');
                return;
            }
            try {
                const form = new FormData();
                form.append('product_key', ACTIVE_PRODUCT_ITEM.product_key);
                form.append('variant_key', ACTIVE_PRODUCT_ITEM.variant_key);
                form.append('price_no_screen', String(priceNoScreen.toFixed(2)));
                form.append('product_type', productType);
                form.append('is_available', isAvailable ? '1' : '0');

                const req = await fetch('backend/products_admin.php?action=update', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': CSRF_TOKEN },
                    body: form
                });
                const res = await req.json();
                if (!res || res.status !== 'success') {
                    throw new Error(res?.message || 'Update failed');
                }

                closeModal('product-modal');
                showCustomAlert('success', 'Price Updated', 'Product pricing has been updated for new quotations.');
                await fetchProductData();
            } catch (err) {
                showCustomAlert('error', 'Update Failed', String(err.message || 'Unable to update product price.'));
            }
        }

        async function saveProductCreate() {
            const productName = String(document.getElementById('pc-product-name')?.value || '').trim();
            const variantLabel = String(document.getElementById('pc-variant-label')?.value || '').trim();
            const priceNoScreen = parseFloat(document.getElementById('pc-price-no-screen')?.value || '');
            const productType = String(document.getElementById('pc-product-type')?.value || 'others').trim().toLowerCase();
            const isAvailable = document.getElementById('pc-is-available')?.checked === true;

            if (productName.length < 2) {
                showCustomAlert('error', 'Invalid Product Name', 'Product name must be at least 2 characters.');
                return;
            }
            if (variantLabel.length < 2) {
                showCustomAlert('error', 'Invalid Variant Label', 'Variant label must be at least 2 characters.');
                return;
            }
            if (!Number.isFinite(priceNoScreen) || priceNoScreen <= 0) {
                showCustomAlert('error', 'Invalid Price', 'Base price must be greater than 0.');
                return;
            }
            if (!['windows', 'doors', 'sliding', 'partitions', 'railings', 'accessories', 'others'].includes(productType)) {
                showCustomAlert('error', 'Invalid Type', 'Please select a valid product type.');
                return;
            }

            try {
                const form = new FormData();
                form.append('product_name', productName);
                form.append('variant_label', variantLabel);
                form.append('price_no_screen', String(priceNoScreen.toFixed(2)));
                form.append('product_type', productType);
                form.append('is_available', isAvailable ? '1' : '0');

                const req = await fetch('backend/products_admin.php?action=create', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': CSRF_TOKEN },
                    body: form
                });
                const res = await req.json();
                if (!res || res.status !== 'success') {
                    throw new Error(res?.message || 'Create failed');
                }

                closeModal('product-create-modal');
                showCustomAlert('success', 'Variant Created', 'New product variant is now available for quotation.');
                PRODUCT_PAGE = 1;
                await fetchProductData();
            } catch (err) {
                showCustomAlert('error', 'Create Failed', String(err.message || 'Unable to create product variant.'));
            }
        }

        async function deleteProductVariant(itemKey) {
            const [productKey, variantKey] = String(itemKey || '').split('__');
            if (!productKey || !variantKey) return;
            const item = PRODUCT_DATA.find((row) => row.product_key === productKey && row.variant_key === variantKey);
            if (!item) return;

            const productName = item.product_name || item.product_key;
            const variantLabel = item.variant_label || item.variant_key;

            const confirmDelete = await Swal.fire({
                title: 'Delete this product variant?',
                html: `
                    <div class="text-left text-sm text-zinc-600">
                        <p><strong class="text-zinc-900">Product:</strong> ${escapeHtml(productName)}</p>
                        <p class="mt-1"><strong class="text-zinc-900">Variant:</strong> ${escapeHtml(variantLabel)}</p>
                        <p class="mt-3 text-red-600">This action cannot be undone.</p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete Variant',
                cancelButtonText: 'Cancel',
                buttonsStyling: false,
                customClass: {
                    popup: 'rounded-xl border border-zinc-200 shadow-2xl p-0',
                    title: 'text-left text-lg font-semibold text-zinc-900 px-6 pt-6 pb-0',
                    htmlContainer: 'px-6 py-3',
                    actions: 'px-6 pb-6',
                    confirmButton: 'bg-red-600 text-white px-4 py-2 rounded-md text-xs font-semibold hover:bg-red-700 transition',
                    cancelButton: 'bg-white text-zinc-700 border border-zinc-300 px-4 py-2 rounded-md text-xs font-semibold hover:bg-zinc-50 transition mr-2'
                }
            });

            if (!confirmDelete.isConfirmed) return;

            try {
                const form = new FormData();
                form.append('product_key', productKey);
                form.append('variant_key', variantKey);
                form.append('product_name', productName);
                form.append('variant_label', variantLabel);

                const req = await fetch('backend/products_admin.php?action=delete', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': CSRF_TOKEN },
                    body: form
                });
                const res = await req.json();
                if (!res || res.status !== 'success') {
                    throw new Error(res?.message || 'Delete failed');
                }

                if (ACTIVE_PRODUCT_ITEM && ACTIVE_PRODUCT_ITEM.product_key === productKey && ACTIVE_PRODUCT_ITEM.variant_key === variantKey) {
                    closeModal('product-modal');
                    ACTIVE_PRODUCT_ITEM = null;
                }
                showCustomAlert('success', 'Variant Deleted', 'Product variant has been removed.');
                await fetchProductData();
            } catch (err) {
                showCustomAlert('error', 'Delete Failed', String(err.message || 'Unable to delete product variant.'));
            }
        }

        function openCustomerModal(customerKey) {
            const customer = CUSTOMER_INDEX[customerKey];
            if (!customer) return;
            ACTIVE_CUSTOMER = customer;

            document.getElementById('cm-name').textContent = customer.name || 'Customer';
            document.getElementById('cm-bookings').textContent = String(customer.bookings || 0);
            document.getElementById('cm-total').textContent = `₱${(customer.totalValue || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            document.getElementById('cm-last').textContent = customer.lastActivityTs ? new Date(customer.lastActivityTs).toLocaleString() : (customer.lastActivityRaw || '—');

            CUSTOMER_HISTORY_FILTER_STATUS = 'all';
            CUSTOMER_HISTORY_FILTER_FROM = '';
            CUSTOMER_HISTORY_FILTER_TO = '';
            const statusFilter = document.getElementById('cm-filter-status');
            const fromFilter = document.getElementById('cm-filter-from');
            const toFilter = document.getElementById('cm-filter-to');
            if (statusFilter) statusFilter.value = 'all';
            if (fromFilter) fromFilter.value = '';
            if (toFilter) toFilter.value = '';
            renderCustomerHistory();

            document.getElementById('customer-modal').classList.add('open');
        }

        function bindCustomerHistoryFilters() {
            const statusFilter = document.getElementById('cm-filter-status');
            const fromFilter = document.getElementById('cm-filter-from');
            const toFilter = document.getElementById('cm-filter-to');
            if (statusFilter) {
                statusFilter.addEventListener('change', (e) => {
                    CUSTOMER_HISTORY_FILTER_STATUS = String(e.target.value || 'all').trim().toLowerCase();
                    renderCustomerHistory();
                });
            }
            if (fromFilter) {
                fromFilter.addEventListener('change', (e) => {
                    CUSTOMER_HISTORY_FILTER_FROM = String(e.target.value || '').trim();
                    renderCustomerHistory();
                });
            }
            if (toFilter) {
                toFilter.addEventListener('change', (e) => {
                    CUSTOMER_HISTORY_FILTER_TO = String(e.target.value || '').trim();
                    renderCustomerHistory();
                });
            }
        }

        function getRecordTimestamp(row) {
            const raw = row.created_at || row.date_created || row.install_date || row.date || '';
            if (!raw) return 0;
            if (typeof raw === 'number' || (typeof raw === 'string' && /^[0-9]+$/.test(raw))) {
                const num = Number(raw);
                if (!Number.isFinite(num)) return 0;
                return num > 100000000000 ? num : num * 1000;
            }
            const ts = Date.parse(raw);
            return Number.isNaN(ts) ? 0 : ts;
        }

        function getFilteredCustomerRecords(customer) {
            const rows = Array.isArray(customer?.records) ? [...customer.records] : [];
            rows.sort((a, b) => getRecordTimestamp(b) - getRecordTimestamp(a));
            return rows.filter((row) => {
                const status = String(row.status || 'pending').trim().toLowerCase();
                if (CUSTOMER_HISTORY_FILTER_STATUS !== 'all' && status !== CUSTOMER_HISTORY_FILTER_STATUS) return false;
                const ts = getRecordTimestamp(row);
                if (CUSTOMER_HISTORY_FILTER_FROM) {
                    const fromTs = Date.parse(`${CUSTOMER_HISTORY_FILTER_FROM}T00:00:00`);
                    if (!Number.isNaN(fromTs) && ts && ts < fromTs) return false;
                }
                if (CUSTOMER_HISTORY_FILTER_TO) {
                    const toTs = Date.parse(`${CUSTOMER_HISTORY_FILTER_TO}T23:59:59`);
                    if (!Number.isNaN(toTs) && ts && ts > toTs) return false;
                }
                return true;
            });
        }

        function findLatestReasonForRecord(row) {
            const history = Array.isArray(row?.history) ? [...row.history] : [];
            if (history.length === 0) return '';
            const targetStatus = String(row.status || '').trim().toLowerCase();
            history.sort((a, b) => normalizeAuditTimestamp(b?.timestamp) - normalizeAuditTimestamp(a?.timestamp));
            for (const h of history) {
                const toStatus = String(h?.new_status || h?.to || h?.status || '').trim().toLowerCase();
                if (targetStatus && toStatus && toStatus !== targetStatus) continue;
                const reason = String(h?.reason || '').trim();
                if (reason) return reason;
                const details = String(h?.details || '').trim();
                if (/reason:/i.test(details)) {
                    return details.replace(/^.*reason:\s*/i, '').trim();
                }
            }
            return '';
        }

        function renderCustomerHistory() {
            const history = document.getElementById('cm-history');
            if (!history) return;
            history.innerHTML = '';
            if (!ACTIVE_CUSTOMER) {
                history.innerHTML = `<div class="text-xs text-zinc-500">No customer selected.</div>`;
                return;
            }

            const records = getFilteredCustomerRecords(ACTIVE_CUSTOMER);
            records.slice(0, 20).forEach((row) => {
                const id = row.id || '—';
                const product = (row.product || '—').split('(')[0].trim();
                const status = row.status || 'Pending';
                const date = row.install_date || row.created_at || row.date_created || '—';
                const price = parseFloat(row.price) || 0;
                const reason = findLatestReasonForRecord(row);
                const reasonHtml = reason ? `<div class="sm:col-span-12 text-[10px] text-zinc-500 leading-tight pt-0.5">Reason: ${escapeHtml(reason)}</div>` : '';
                history.innerHTML += `<div class="grid grid-cols-1 sm:grid-cols-12 gap-1.5 px-2.5 py-2 border border-zinc-200 rounded-lg bg-white">
                    <div class="sm:hidden grid grid-cols-2 gap-x-2 gap-y-1 text-[10px]">
                        <div class="text-zinc-400 font-semibold uppercase tracking-wide">Ref</div>
                        <div class="font-mono text-zinc-600 break-all">${id}</div>
                        <div class="text-zinc-400 font-semibold uppercase tracking-wide">Product</div>
                        <div class="text-primary break-words">${product}</div>
                        <div class="text-zinc-400 font-semibold uppercase tracking-wide">Date</div>
                        <div class="text-zinc-600">${date}</div>
                        <div class="text-zinc-400 font-semibold uppercase tracking-wide">Amount</div>
                        <div class="font-mono text-primary">₱${price.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                        <div class="text-zinc-400 font-semibold uppercase tracking-wide">Status</div>
                        <div class="text-zinc-500">${status}</div>
                    </div>
                    <div class="hidden sm:block sm:col-span-3 font-mono text-[10px] text-zinc-500 break-all leading-tight">${id}</div>
                    <div class="hidden sm:block sm:col-span-3 text-[11px] text-primary break-words leading-tight">${product}</div>
                    <div class="hidden sm:block sm:col-span-2 text-[11px] text-zinc-600 leading-tight">${date}</div>
                    <div class="hidden sm:block sm:col-span-2 text-[11px] sm:text-right font-mono text-primary leading-tight">₱${price.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                    <div class="hidden sm:block sm:col-span-2 text-[10px] sm:text-right text-zinc-500 leading-tight">${status}</div>
                    ${reasonHtml}
                </div>`;
            });

            if (records.length === 0) {
                history.innerHTML = `<div class="text-xs text-zinc-500">No history for current filter.</div>`;
            }
        }

        function exportCustomerHistoryCSV() {
            if (!ACTIVE_CUSTOMER) {
                showCustomAlert('error', 'No Customer', 'Open a customer profile before exporting.');
                return;
            }
            const rows = getFilteredCustomerRecords(ACTIVE_CUSTOMER);
            if (rows.length === 0) {
                showCustomAlert('error', 'No Data', 'No records match the current history filter.');
                return;
            }

            const escapeCsv = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
            let csv = 'Booking ID,Customer,Product,Status,Install Date,Created At,Amount\n';
            rows.forEach((row) => {
                const line = [
                    row.id || '',
                    ACTIVE_CUSTOMER.name || '',
                    row.product || '',
                    row.status || '',
                    row.install_date || '',
                    row.created_at || row.date_created || '',
                    parseFloat(row.price) || 0
                ].map(escapeCsv).join(',');
                csv += `${line}\n`;
            });

            const dateTag = new Date().toISOString().slice(0, 10);
            const safeName = String(ACTIVE_CUSTOMER.name || 'customer')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '');
            const link = document.createElement('a');
            link.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
            link.download = `${safeName || 'customer'}_timeline_${dateTag}.csv`;
            link.click();
        }


        function showCustomAlert(type, title, message) {
            const isSuccess = type === 'success';
            const iconBg = isSuccess ? 'bg-zinc-100' : 'bg-red-50';
            const iconColor = isSuccess ? 'text-zinc-900' : 'text-red-600';
            const iconSvg = isSuccess 
                ? '<svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>' 
                : '<svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';

            Swal.fire({
                html: `
                    <div class="flex flex-col items-center pt-2 pb-1">
                        <div class="w-12 h-12 rounded-full ${iconBg} ${iconColor} flex items-center justify-center mb-4">
                            ${iconSvg}
                        </div>
                        <h3 class="text-base font-bold text-zinc-900 mb-1">${title}</h3>
                        <p class="text-xs text-zinc-500 text-center max-w-[240px] leading-relaxed">${message}</p>
                    </div>
                `,
                showConfirmButton: true,
                confirmButtonText: 'Okay',
                buttonsStyling: false,
                customClass: {
                    popup: 'rounded-2xl border border-zinc-200 shadow-xl p-0',
                    confirmButton: 'mt-4 bg-zinc-900 text-white px-8 py-2.5 rounded-lg text-xs font-bold hover:bg-zinc-800 transition w-full'
                },
                width: 320,
                padding: '2rem'
            });
        }

        async function openRecoveryRegenerateModal() {
            const prompt = await Swal.fire({
                title: 'Regenerate Recovery Code',
                html: `
                    <div class="text-left space-y-3">
                        <p class="text-xs text-zinc-500">Enter your current password and authenticator code to generate a new recovery code.</p>
                        <input id="regen-current-pass" type="password" class="w-full h-10 border border-zinc-300 rounded-md px-3 text-sm bg-white outline-none focus:border-zinc-900 transition" placeholder="Current password">
                        <input id="regen-totp" type="text" maxlength="6" class="w-full h-10 border border-zinc-300 rounded-md px-3 text-sm bg-white outline-none focus:border-zinc-900 transition" placeholder="Authenticator code">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Regenerate',
                cancelButtonText: 'Cancel',
                preConfirm: () => {
                    const current_password = String(document.getElementById('regen-current-pass')?.value || '');
                    const totp_code = String(document.getElementById('regen-totp')?.value || '').replace(/\D+/g, '');
                    if (!current_password) {
                        Swal.showValidationMessage('Current password is required.');
                        return false;
                    }
                    if (!/^\d{6}$/.test(totp_code)) {
                        Swal.showValidationMessage('Authenticator code must be 6 digits.');
                        return false;
                    }
                    return { current_password, totp_code };
                },
                buttonsStyling: false,
                customClass: {
                    popup: 'rounded-xl border border-zinc-200 shadow-2xl p-0',
                    title: 'text-left text-lg font-semibold text-zinc-900 px-6 pt-6 pb-0',
                    htmlContainer: 'px-6 pt-3 pb-0',
                    actions: 'w-full px-6 pb-6 pt-4 gap-2 justify-end',
                    confirmButton: 'h-9 px-4 rounded-md bg-zinc-900 text-white text-xs font-semibold hover:bg-zinc-800 transition',
                    cancelButton: 'h-9 px-4 rounded-md border border-zinc-300 bg-white text-zinc-700 text-xs font-semibold hover:bg-zinc-50 transition'
                },
                width: 470
            });

            if (!prompt.isConfirmed || !prompt.value) return;

            try {
                const req = await fetch('backend/auth.php?action=recovery_regenerate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify(prompt.value)
                });
                const res = await req.json();
                if (!req.ok || res.status !== 'success' || !res.recovery_code) {
                    throw new Error(res.message || 'Failed to regenerate recovery code.');
                }

                await Swal.fire({
                    title: 'New Recovery Code',
                    html: `
                        <div class="text-left">
                            <p class="text-xs text-zinc-500 mb-3">Save this offline now. Previous recovery code is no longer valid.</p>
                            <code class="block text-sm font-bold bg-zinc-100 border border-zinc-200 rounded p-3 break-all">${res.recovery_code}</code>
                        </div>
                    `,
                    confirmButtonText: 'Saved',
                    buttonsStyling: false,
                    customClass: {
                        popup: 'rounded-xl border border-zinc-200 shadow-2xl p-0',
                        title: 'text-left text-lg font-semibold text-zinc-900 px-6 pt-6 pb-0',
                        htmlContainer: 'px-6 pt-3 pb-0',
                        actions: 'w-full px-6 pb-6 pt-4 justify-end',
                        confirmButton: 'h-9 px-4 rounded-md bg-zinc-900 text-white text-xs font-semibold hover:bg-zinc-800 transition'
                    },
                    width: 470
                });
            } catch (err) {
                showCustomAlert('error', 'Regeneration Failed', err.message || 'Unable to regenerate recovery code.');
            }
        }

        async function saveChanges() {
            if (!ACTIVE_ID) return;
            const newStatus = document.getElementById('m-status').value;
            const installDateInput = document.getElementById('m-install-date');
            const selectedInstallDate = String(installDateInput?.value || '').trim();
            const currentStatus = String(installDateInput?.dataset.currentStatus || '').trim();
            const currentInstallDate = String(installDateInput?.dataset.currentInstallDate || '').trim();
            const btn = document.querySelector('#action-modal button:last-child');
            const originalText = btn.innerText;
            let statusReason = '';
            let reasonCode = '';

            const statusChanged = newStatus !== '' && newStatus !== currentStatus;
            const dateChanged = selectedInstallDate !== '' && selectedInstallDate !== currentInstallDate;
            const effectiveDate = dateChanged ? selectedInstallDate : currentInstallDate;
            if (!statusChanged && !dateChanged) {
                showCustomAlert('error', 'No Changes', 'Please change status or schedule date before saving.');
                return;
            }
            if ((newStatus === 'Site Visit' || newStatus === 'Installation') && !effectiveDate) {
                showCustomAlert('error', 'Schedule Required', `${newStatus} requires a valid schedule date.`);
                return;
            }

            if (statusChanged && (newStatus === 'Cancelled' || newStatus === 'Void')) {
                const reasonOptions = newStatus === 'Cancelled'
                    ? [
                        { value: 'customer_request', label: 'Customer Request' },
                        { value: 'unresponsive_customer', label: 'Unresponsive Customer' },
                        { value: 'duplicate_booking', label: 'Duplicate Booking' },
                        { value: 'pricing_issue', label: 'Pricing Issue' },
                        { value: 'service_area_limit', label: 'Service Area Limit' },
                        { value: 'other', label: 'Other' }
                    ]
                    : [
                        { value: 'quotation_expired', label: 'Quotation Expired' },
                        { value: 'no_response_after_quote', label: 'No Response After Quote' },
                        { value: 'duplicate_submission', label: 'Duplicate Submission' },
                        { value: 'invalid_request', label: 'Invalid Request' },
                        { value: 'other', label: 'Other' }
                    ];
                const confirm = await Swal.fire({
                    title: `${newStatus} this booking?`,
                    html: `
                        <div class="text-left">
                            <p class="text-sm text-zinc-600 mb-3">This is a destructive transition. Select reason category, then add optional notes.</p>
                            <select id="status-reason-code" class="w-full h-10 border border-zinc-300 rounded-md px-3 text-sm text-zinc-900 bg-white outline-none focus:border-zinc-900 transition mb-3">
                                <option value="">Select reason category...</option>
                                ${reasonOptions.map((opt) => `<option value="${opt.value}">${opt.label}</option>`).join('')}
                            </select>
                            <textarea id="status-reason-input" class="w-full min-h-[100px] border border-zinc-300 rounded-md px-3 py-2 text-sm text-zinc-900 bg-white outline-none focus:border-zinc-900 transition" placeholder="Optional notes"></textarea>
                        </div>
                    `,
                    showCancelButton: true,
                    showCloseButton: true,
                    confirmButtonText: `Confirm ${newStatus}`,
                    cancelButtonText: 'Cancel',
                    preConfirm: () => {
                        const el = document.getElementById('status-reason-input');
                        const codeEl = document.getElementById('status-reason-code');
                        const val = String(el?.value || '').trim();
                        const code = String(codeEl?.value || '').trim();
                        if (!code) {
                            Swal.showValidationMessage('Reason category is required.');
                            return false;
                        }
                        return { val, code };
                    },
                    buttonsStyling: false,
                    customClass: {
                        popup: 'rounded-xl border border-zinc-200 shadow-2xl p-0',
                        title: 'text-left text-lg font-semibold text-zinc-900 px-6 pt-6 pb-0',
                        htmlContainer: 'px-6 pt-3 pb-0',
                        actions: 'w-full px-6 pb-6 pt-4 gap-2 justify-end',
                        confirmButton: 'h-9 px-4 rounded-md bg-zinc-900 text-white text-xs font-semibold hover:bg-zinc-800 transition',
                        cancelButton: 'h-9 px-4 rounded-md border border-zinc-300 bg-white text-zinc-700 text-xs font-semibold hover:bg-zinc-50 transition',
                        closeButton: 'text-zinc-400 hover:text-zinc-700 transition !top-3 !right-3'
                    },
                    width: 470
                });
                if (!confirm.isConfirmed) return;
                statusReason = String(confirm.value?.val || '').trim();
                reasonCode = String(confirm.value?.code || '').trim();
            } else if (statusChanged && newStatus === 'Completed') {
                const confirm = await Swal.fire({
                    title: 'Complete this booking?',
                    html: `
                        <div class="text-left">
                            <p class="text-sm text-zinc-600 mb-3">Add completion notes for audit trail.</p>
                            <select id="status-reason-code" class="w-full h-10 border border-zinc-300 rounded-md px-3 text-sm text-zinc-900 bg-white outline-none focus:border-zinc-900 transition mb-3">
                                <option value="installation_done">Installation Done</option>
                                <option value="project_handover_done">Project Handover Done</option>
                                <option value="other">Other</option>
                            </select>
                            <textarea id="status-reason-input" class="w-full min-h-[112px] border border-zinc-300 rounded-md px-3 py-2 text-sm text-zinc-900 bg-white outline-none focus:border-zinc-900 transition" placeholder="Completion notes (required)"></textarea>
                        </div>
                    `,
                    showCancelButton: true,
                    showCloseButton: true,
                    confirmButtonText: 'Mark Completed',
                    cancelButtonText: 'Cancel',
                    preConfirm: () => {
                        const el = document.getElementById('status-reason-input');
                        const codeEl = document.getElementById('status-reason-code');
                        const val = String(el?.value || '').trim();
                        const code = String(codeEl?.value || '').trim();
                        if (val.length < 5) {
                            Swal.showValidationMessage('Completion note is required (minimum 5 characters).');
                            return false;
                        }
                        return { val, code };
                    },
                    buttonsStyling: false,
                    customClass: {
                        popup: 'rounded-xl border border-zinc-200 shadow-2xl p-0',
                        title: 'text-left text-lg font-semibold text-zinc-900 px-6 pt-6 pb-0',
                        htmlContainer: 'px-6 pt-3 pb-0',
                        actions: 'w-full px-6 pb-6 pt-4 gap-2 justify-end',
                        confirmButton: 'h-9 px-4 rounded-md bg-zinc-900 text-white text-xs font-semibold hover:bg-zinc-800 transition',
                        cancelButton: 'h-9 px-4 rounded-md border border-zinc-300 bg-white text-zinc-700 text-xs font-semibold hover:bg-zinc-50 transition',
                        closeButton: 'text-zinc-400 hover:text-zinc-700 transition !top-3 !right-3'
                    },
                    width: 470
                });
                if (!confirm.isConfirmed) return;
                statusReason = String(confirm.value?.val || '').trim();
                reasonCode = String(confirm.value?.code || 'installation_done').trim();
            }

            btn.innerText = "Processing..."; btn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('id', ACTIVE_ID);
                formData.append('status', newStatus);
                formData.append('reason', statusReason);
                formData.append('reason_code', reasonCode);
                if (dateChanged) {
                    formData.append('install_date', selectedInstallDate);
                }

                const req = await fetch('backend/update_status.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': CSRF_TOKEN },
                    body: formData
                });
                const res = await req.json();

                if(res.status === 'success') {
                    closeModal('action-modal');
                    showCustomAlert('success', 'Status Updated', `Booking moved to ${newStatus}.`);
                    await fetchLatestData(); 
                } else {
                    const details = (res && typeof res.details === 'string' && res.details.trim() !== '')
                        ? ` (${res.details.trim()})`
                        : '';
                    throw new Error(String(res.message || 'Update failed.') + details);
                }
            } catch (err) {
                showCustomAlert('error', 'Update Failed', err.message);
            } finally {
                btn.innerText = originalText; btn.disabled = false;
            }
        }

        async function submitDateBlock() {
            const date = document.getElementById('block-date').value;
            const reason = document.getElementById('block-reason').value;
            if(!date) return showCustomAlert('error', 'Missing Date', 'Please select a date to block.');

            try {
                const formData = new FormData();
                formData.append('date', date);
                formData.append('reason', reason);
                const req = await fetch('backend/block_date.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': CSRF_TOKEN },
                    body: formData
                });
                const res = await req.json();
                if (!res || res.status !== 'success') {
                    const conflictInfo = (res && Number.isFinite(Number(res.conflict_count)))
                        ? ` (${res.conflict_count} active booking${Number(res.conflict_count) > 1 ? 's' : ''})`
                        : '';
                    throw new Error((res && res.message ? res.message : 'Failed to block date.') + conflictInfo);
                }
                
                closeModal('block-modal', true);
                showCustomAlert('success', 'Date Blocked', `Customers can no longer book on ${date}.`);
                
                document.getElementById('block-date').value = '';
                document.getElementById('block-reason').value = '';
                
                if(calendar) {
                    calendar.refetchEvents();
                    setTimeout(() => {
                        calendar.updateSize(); 
                    }, 300);
                }
            } catch (err) {
                showCustomAlert('error', 'Error', err.message || 'Failed to block date.');
            }
        }

        async function runRetentionPurge() {
            const confirmRes = await Swal.fire({
                title: 'Run Data Retention Cleanup?',
                html: '<p class="text-sm text-zinc-600">This will remove customer repository records with no activity for 12+ months. Booking history remains.</p>',
                showCancelButton: true,
                confirmButtonText: 'Run Cleanup',
                cancelButtonText: 'Cancel',
                buttonsStyling: false,
                customClass: {
                    popup: 'rounded-xl border border-zinc-200 shadow-2xl p-0',
                    title: 'text-left text-lg font-semibold text-zinc-900 px-6 pt-6 pb-0',
                    htmlContainer: 'px-6 pt-3 pb-0',
                    actions: 'w-full px-6 pb-6 pt-4 gap-2 justify-end',
                    confirmButton: 'h-9 px-4 rounded-md bg-zinc-900 text-white text-xs font-semibold hover:bg-zinc-800 transition',
                    cancelButton: 'h-9 px-4 rounded-md border border-zinc-300 bg-white text-zinc-700 text-xs font-semibold hover:bg-zinc-50 transition'
                }
            });
            if (!confirmRes.isConfirmed) return;

            try {
                const req = await fetch('backend/retention_cleanup.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({ dry_run: false })
                });
                const res = await req.json();
                if (!res || res.status !== 'success') {
                    throw new Error((res && res.message) ? res.message : 'Retention purge failed.');
                }
                showCustomAlert(
                    'success',
                    'Retention Purge Complete',
                    `Scanned ${Number(res.scanned_customers || 0)} customer records. Purged ${Number(res.purged_customers || 0)} record(s).`
                );
                await loadRetentionStatus();
                await fetchLatestData({ silent: true });
                renderCustomerDB();
            } catch (err) {
                showCustomAlert('error', 'Retention Purge Failed', err.message || 'Unable to run retention cleanup.');
            }
        }

        async function loadRetentionStatus() {
            const label = document.getElementById('retention-status-label');
            if (!label) return;
            try {
                const req = await fetch('backend/retention_cleanup.php', {
                    method: 'GET',
                    headers: { 'X-CSRF-Token': CSRF_TOKEN }
                });
                const res = await req.json();
                if (!res || res.status !== 'success') {
                    label.textContent = 'Retention: unavailable';
                    return;
                }
                const state = (res && typeof res.state === 'object' && res.state) ? res.state : null;
                if (!state || !state.last_run_at) {
                    label.textContent = 'Retention: no run yet';
                    return;
                }
                const lastRun = new Date(Number(state.last_run_at) * 1000);
                const runText = Number.isNaN(lastRun.getTime())
                    ? 'unknown'
                    : lastRun.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
                const purged = Number(state.purged_customers || 0);
                label.textContent = `Retention • Last: ${runText} • Purged: ${purged}`;
            } catch (_) {
                label.textContent = 'Retention: unavailable';
            }
        }

        function openModal(id) {
            const data = GLOBAL_DATA.find(item => item.id === id);
            if (!data) return;
            ACTIVE_ID = id;
            
            document.getElementById('m-cust').textContent = data.customer_name_snapshot || data.customer || data.name;
            document.getElementById('m-id').textContent = `REF: ${data.id}`;
            document.getElementById('m-prod').textContent = (data.product||'').split('(')[0];
            document.getElementById('m-price').textContent = `₱${(parseFloat(data.price)||0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            document.getElementById('m-date').textContent = data.install_date || data.date_created;
            const installDateInput = document.getElementById('m-install-date');
            const currentInstallDate = String(data.install_date || '').trim();
            installDateInput.value = currentInstallDate;
            installDateInput.dataset.currentInstallDate = currentInstallDate;
            installDateInput.dataset.currentStatus = data.status || 'Pending';
            const now = new Date();
            const yyyy = now.getFullYear();
            const mm = String(now.getMonth() + 1).padStart(2, '0');
            const dd = String(now.getDate()).padStart(2, '0');
            installDateInput.min = `${yyyy}-${mm}-${dd}`;
            installDateInput.disabled = false;
            installDateInput.classList.remove('opacity-50', 'cursor-not-allowed');
            
            const statusSelect = document.getElementById('m-status');
            const saveBtn = document.querySelector('button[onclick="saveChanges()"]'); 
            const currentStatus = data.status || 'Pending';
            
            statusSelect.innerHTML = '';
            statusSelect.disabled = false;
            statusSelect.classList.remove('opacity-50', 'cursor-not-allowed');
            if(saveBtn) saveBtn.style.display = 'block';

            let options = [];

            if (TERMINAL_STATUSES.includes(currentStatus)) {
                options = [{ val: currentStatus, txt: currentStatus + " (Locked)" }];
                statusSelect.disabled = true;
                statusSelect.classList.add('opacity-50', 'cursor-not-allowed');
                installDateInput.disabled = true;
                installDateInput.classList.add('opacity-50', 'cursor-not-allowed');
                if(saveBtn) saveBtn.style.display = 'none';
                
                document.getElementById('m-id').innerHTML = `REF: ${data.id} <span class="ml-2 bg-zinc-800 text-white px-1.5 py-0.5 rounded text-[9px] uppercase tracking-wider">LOCKED</span>`;

            } else if (currentStatus === 'Cancelled') {
                options = [
                    { val: 'Cancelled', txt: 'Cancelled (Current)' },
                    { val: 'Pending',   txt: 'Pending (Re-open)' } // The only way out
                ];
                document.getElementById('m-id').textContent = `REF: ${data.id}`;

            } else {
                document.getElementById('m-id').textContent = `REF: ${data.id}`;

                options.push({
                    val: currentStatus,
                    txt: `${STATUS_LABELS[currentStatus] || currentStatus} (Current)`
                });

                const allowed = WORKFLOW_TRANSITIONS[currentStatus] || [];
                if (allowed.length > 0) {
                    options.push({ val: 'DISABLED', txt: '──────────' });
                    allowed.forEach((nextStatus) => {
                        options.push({
                            val: nextStatus,
                            txt: STATUS_LABELS[nextStatus] || nextStatus
                        });
                    });
                }
            }

            options.forEach(opt => {
                const el = document.createElement('option');
                el.value = opt.val;
                el.textContent = opt.txt;
                if(opt.val === 'DISABLED') el.disabled = true;
                statusSelect.appendChild(el);
            });

            statusSelect.value = currentStatus;

            document.getElementById('action-modal').classList.add('open');
        }

        function openBlockModal(evt) {
            if (evt) {
                evt.preventDefault();
                evt.stopPropagation();
            }
            BLOCK_MODAL_IGNORE_BACKDROP_ONCE = true;
            setTimeout(() => {
                BLOCK_MODAL_IGNORE_BACKDROP_ONCE = false;
            }, 700);
            document.getElementById('block-modal').classList.add('open');
        }
        function closeModal(modalId, force = false) {
            document.getElementById(modalId).classList.remove('open');
        }

        function downloadCSV() {
            if(GLOBAL_DATA.length === 0) return;
            let csv = "ID,Name,Product,Price,Status,Date\n";
            GLOBAL_DATA.forEach(row => { const name = (row.customer_name_snapshot || row.customer || row.name || '').replace(/,/g, ''); csv += `${row.id},${name},"${row.product}",${row.price},${row.status},${row.install_date}\n`; });
            const link = document.createElement("a"); link.href = "data:text/csv;charset=utf-8," + encodeURI(csv); link.download = "jth_data.csv"; link.click();
        }

        function logout() { fetch('backend/auth.php?action=logout').then(() => window.location.href='admin-login.html'); }
    </script>
</body>
</html>
