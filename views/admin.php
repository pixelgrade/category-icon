<?php
/**
 * Represents the view for the administration dashboard.
 *
 * This includes the header, options, and other information that should
 * provide the user interface to the end user.
 *
 */ ?>

<div class="wrap" id="category_icons_form">
	<div id="icon-options-general" class="icon32"></div>
	<form action="options.php" method="post">
		<?php
		settings_fields('pix_category_icons');
		do_settings_sections('pix-category-icons'); ?>
		<input name="Submit" type="submit" value="<?php _e('Save Changes', 'category_icons_txtd'); ?>" />
	</form>
</div>