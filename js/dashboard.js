// Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
});

function initializeDashboard() {
    // Load transactions
    loadTransactions();

    // Setup navigation
    setupNavigation();

    // Setup mobile menu
    setupMobileMenu();

    // Setup transfer amount update
    setupTransferForm();

    // Load initial section
    showSection('overview');
}

function setupTransferForm() {
    const transferAmountInput = document.getElementById('transferAmount');
    const summaryAmount = document.getElementById('summaryAmount');
    const summaryTotal = document.getElementById('summaryTotal');

    if (transferAmountInput) {
        transferAmountInput.addEventListener('input', function() {
            const amount = parseFloat(this.value) || 0;
            if (summaryAmount) {
                summaryAmount.textContent = 'Rs' + amount.toFixed(2);
            }
            if (summaryTotal) {
                summaryTotal.textContent = 'Rs' + amount.toFixed(2);
            }
        });
    }
}

function loadTransactions() {
    const recentList = document.getElementById('recentTransactionsList');
    const tableBody = document.getElementById('transactionsTableBody');

    if (recentList) {
        recentList.innerHTML = '';
        // Recent transactions will be loaded from PHP
    }

    if (tableBody) {
        tableBody.innerHTML = '';
        // All transactions will be loaded from PHP
    }
}

function setupNavigation() {
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const section = this.getAttribute('data-section');
            if (section) {
                showSection(section);

                // Update active nav item
                navItems.forEach(nav => nav.classList.remove('active'));
                this.classList.add('active');

                // Close mobile menu
                document.querySelector('.sidebar').classList.remove('active');
            }
        });
    });
}

function showSection(sectionName) {
    // Hide all sections
    const sections = document.querySelectorAll('.content-section');
    sections.forEach(section => section.classList.remove('active'));

    // Show selected section
    const targetSection = document.getElementById(sectionName);
    if (targetSection) {
        targetSection.classList.add('active');
    }

    // Update page title
    const pageTitle = document.getElementById('pageTitle');
    if (pageTitle) {
        const titles = {
            'overview': 'Dashboard Overview',
            'accounts': 'My Accounts',
            'transactions': 'Transaction History',
            'transfer': 'Transfer Money',
            'cards': 'My Cards',
            'settings': 'Account Settings'
        };
        pageTitle.textContent = titles[sectionName] || 'Dashboard';
    }
}

function setupMobileMenu() {
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });

        // Close sidebar when clicking outside
        document.addEventListener('click', function(e) {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });
    }
}

// Global functions for navigation
window.showSection = showSection;

// Logout function
function logout() {
    if(confirm('Are you sure you want to logout?')) {
        window.location.href = '../php/logout.php';
    }
}

// Filter transactions by type
function filterTransactions(type) {
    const rows = document.querySelectorAll('#transactionsTableBody .table-row[data-type]');

    rows.forEach(row => {
        const rowType = row.getAttribute('data-type');

        if (type === 'all' ||
            (type === 'income' && rowType === 'income') ||
            (type === 'expense' && rowType === 'expense') ||
            (type === 'transfer' && (rowType === 'transfer_sent' || rowType === 'transfer_received'))) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Password modal functions
function showPasswordModal() {
    const modal = document.getElementById('passwordModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closePasswordModal() {
    const modal = document.getElementById('passwordModal');
    if (modal) {
        modal.style.display = 'none';
        document.getElementById('currentPassword').value = '';
        document.getElementById('newPassword').value = '';
        document.getElementById('confirmPassword').value = '';
    }
}

function validatePasswordForm() {
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;

    if (newPassword !== confirmPassword) {
        alert('New passwords do not match!');
        return false;
    }

    if (newPassword.length < 6) {
        alert('Password must be at least 6 characters long!');
        return false;
    }

    return true;
}

// Download statement function
function downloadStatement() {
    const rows = document.querySelectorAll('#transactionsTableBody .table-row');

    if (rows.length === 0) {
        alert('No transactions to download!');
        return;
    }

    let csvContent = 'Date,From,Category,Amount,Description\n';

    rows.forEach(row => {
        const cells = row.querySelectorAll('.table-cell');
        if (cells.length >= 5) {
            const date = cells[0].textContent.trim();
            const from = cells[1].textContent.trim();
            const category = cells[2].textContent.trim();
            const amount = cells[3].textContent.trim();
            const description = cells[4].textContent.trim();

            csvContent += `"${date}","${from}","${category}","${amount}","${description}"\n`;
        }
    });

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);

    const today = new Date();
    const dateStr = today.toISOString().split('T')[0];

    link.setAttribute('href', url);
    link.setAttribute('download', `transaction_statement_${dateStr}.csv`);
    link.style.visibility = 'hidden';

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Make functions globally available
window.showPasswordModal = showPasswordModal;
window.closePasswordModal = closePasswordModal;
window.validatePasswordForm = validatePasswordForm;
window.downloadStatement = downloadStatement;
window.filterTransactions = filterTransactions;

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('passwordModal');
    if (event.target === modal) {
        closePasswordModal();
    }
});

// Setup filter dropdown
const filterSelect = document.querySelector('.filter-select');
if (filterSelect) {
    filterSelect.addEventListener('change', function() {
        const value = this.value.toLowerCase();
        if (value === 'all transactions') {
            filterTransactions('all');
        } else if (value === 'transfers') {
            filterTransactions('transfer');
        } else {
            filterTransactions(value);
        }
    });
}