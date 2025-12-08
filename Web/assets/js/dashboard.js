document.addEventListener("DOMContentLoaded", function() {
    
    // 1. Clock Logic
    function updateTime() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const timeString = now.toLocaleTimeString();
        const dateString = now.toLocaleDateString(undefined, options);
        
        const dateElement = document.getElementById('current-date');
        if(dateElement) {
            dateElement.innerHTML = `${dateString} <br> <span style="font-size: 1.2em; font-weight: bold; color: #34495e;">${timeString}</span>`;
        }
    }
    setInterval(updateTime, 1000);
    updateTime();

    // 2. Initialize Sidebar State from LocalStorage
    const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
    if(isCollapsed) {
        document.getElementById('sidebar').classList.add('collapsed');
        document.querySelector('.main-content').classList.add('expanded');
    }
});

// 3. Toggle Sidebar Size (Mini vs Full)
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const content = document.querySelector('.main-content');
    
    sidebar.classList.toggle('collapsed');
    content.classList.toggle('expanded');

    // Save preference
    const isCollapsed = sidebar.classList.contains('collapsed');
    localStorage.setItem('sidebar-collapsed', isCollapsed);
}

// 4. Toggle Accordion Menus
function toggleMenu(element) {
    const parent = element.parentElement; // The <li>
    const sidebar = document.getElementById('sidebar');

    // If sidebar is collapsed, clicking expands it temporarily or ignores
    if(sidebar.classList.contains('collapsed')) {
        toggleSidebar(); // Auto-expand if user clicks a menu item while collapsed
        // return; // Optional: Keep it collapsed and use hover only
    }

    // Close other open menus (Optional - Accordion style)
    const allDropdowns = document.querySelectorAll('.dropdown');
    allDropdowns.forEach(d => {
        if(d !== parent) {
            d.classList.remove('open');
        }
    });

    // Toggle current
    parent.classList.toggle('open');
}