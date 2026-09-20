<nav class="sidebar d-flex flex-column p-3">
	<div class="brand fs-5 mb-4"><i class="bi bi-calendar-check"></i> Booking App</div>
	<ul class="nav nav-pills flex-column mb-auto">
		<li class="nav-item">
			<a href="<?php echo APP_URL; ?>/admin/dashboard/index.php" class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
				<i class="bi bi-speedometer2 me-2"></i>Dashboard
			</a>
		</li>
		<li class="nav-item">
			<a href="<?php echo APP_URL; ?>/admin/services/index.php" class="nav-link <?php echo $current_page === 'services' ? 'active' : ''; ?>">
				<i class="bi bi-briefcase me-2"></i>Services
			</a>
		</li>
		<li class="nav-item">
			<a href="<?php echo APP_URL; ?>/admin/links/index.php" class="nav-link <?php echo $current_page === 'links' ? 'active' : ''; ?>">
				<i class="bi bi-link-45deg me-2"></i>One-Time Links
			</a>
		</li>
		<li class="nav-item">
			<a href="<?php echo APP_URL; ?>/admin/bookings/index.php" class="nav-link <?php echo $current_page === 'bookings' ? 'active' : ''; ?>">
				<i class="bi bi-journal-check me-2"></i>Bookings
			</a>
		</li>
		<li class="nav-item">
			<a href="<?php echo APP_URL; ?>/admin/settings/index.php" class="nav-link <?php echo $current_page === 'settings' ? 'active' : ''; ?>">
				<i class="bi bi-gear me-2"></i>Settings
			</a>
		</li>
	</ul>
</nav>
