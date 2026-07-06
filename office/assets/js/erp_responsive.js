document.addEventListener('DOMContentLoaded', function () {
  var sidebar = document.querySelector('.sidebar');
  var toggle = document.querySelector('[data-sidebar-toggle]');
  if (!sidebar || !toggle) return;

  var backdrop = document.createElement('div');
  backdrop.className = 'sidebar-backdrop no-print';
  document.body.appendChild(backdrop);

  function openSidebar() {
    sidebar.classList.add('is-open');
    backdrop.classList.add('is-open');
    document.body.classList.add('sidebar-open');
  }

  function closeSidebar() {
    sidebar.classList.remove('is-open');
    backdrop.classList.remove('is-open');
    document.body.classList.remove('sidebar-open');
  }

  toggle.addEventListener('click', function () {
    if (sidebar.classList.contains('is-open')) closeSidebar();
    else openSidebar();
  });

  backdrop.addEventListener('click', closeSidebar);

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeSidebar();
  });

  sidebar.querySelectorAll('nav a').forEach(function (link) {
    link.addEventListener('click', function () {
      if (window.innerWidth < 992) closeSidebar();
    });
  });

  window.addEventListener('resize', function () {
    if (window.innerWidth >= 992) closeSidebar();
  });
});
