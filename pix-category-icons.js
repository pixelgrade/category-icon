(function ($) {
	$(window).load(function () {

		$(document).on('click', '.open_term_icon_upload', function (e) {
			e.preventDefault();
			wp.media.EditTermIconUpload.frame().open();
		});

		// Link any localized strings.
		var l10n = wp.media.view.l10n = typeof _wpMediaViewsL10n === 'undefined' ? {} : _wpMediaViewsL10n;

		wp.media.EditTermIconUpload = {
			frame: function () {
				if (this._frame)
					return this._frame;
				var selection = this.select();
				// create our own media iframe
				this._frame = wp.media({
					id: 'zip_uploader-frame',
					title: 'TermIconUpload',
					filterable: 'uploaded',
					frame: 'post',
					state: 'insert',
					//library: {type: 'zip'},
					multiple: false,  // Set to true to allow multiple files to be selected
					editing: true,
					selection: selection
				});

				var controler =  wp.media.EditTermIconUpload._frame.states.get('insert');
				// force display settings off
				controler.attributes.displaySettings = false;

				//but still keep the reverse button in our modal
				controler.gallerySettings = function( browser ) {
					var library = this.get('library');
					if ( ! library || ! browser ) {
						return;
					}

					library.gallery = library.gallery || new Backbone.Model();
					browser.toolbar.set( 'reverse', {
						text:     l10n.reverseOrder,
						priority: 80,
						click: function() {
							library.reset( library.toArray().reverse() );
						}
					});
				};

				wp.media.EditTermIconUpload._frame.states.add('insert', controler);

				// on update send our attachments ids into a post meta field
				this._frame.on('insert', function ( selection ) {
					//selection = selection || state.get('selection');
					var controller = wp.media.EditTermIconUpload._frame.states.get('insert'),
						library = controller.get('library' ),
					// Need to get all the attachment ids for gallery
					ids = library.pluck('id' );

					var current_selection = controller.get('selection' );

					console.log( current_selection );

					var plm = current_selection.models;

					if ( typeof plm[0] !== "undefined" ) {
						$('#term_icon_value').val( plm[0].id );
					}
				});

				return this._frame;
			},

			select: function () {
				var galleries_ids = $('#term_icon_value').val(),
					shortcode = wp.shortcode.next('gallery', '[gallery ids="' + galleries_ids + '"]'),
					defaultPostId = wp.media.gallery.defaults.id,
					attachments, selection;


				// Bail if we didn't match the shortcode or all of the content.
				if (!shortcode)
					return;

				// Ignore the rest of the match object.
				shortcode = shortcode.shortcode;

				// quit when we don't have images
				//if ( shortcode.get('ids') == '' ) {
				//	return;
				//}

				if (_.isUndefined(shortcode.get('id')) && !_.isUndefined(defaultPostId))
					shortcode.set('id', defaultPostId);

				attachments = wp.media.gallery.attachments(shortcode);
				selection = new wp.media.model.Selection(attachments.models, {
					props: attachments.props.toJSON(),
					multiple: true
				});

				selection.gallery = attachments.gallery;

				// Fetch the query's attachments, and then break ties from the
				// query to allow for sorting.
				selection.more().done(function () {
					// Break ties with the query.
					selection.props.set({query: false});
					selection.unmirror();
					//selection.props.unset('orderby');
				});

				return selection;
			}
		};

		$(wp.media.EditTermIconUpload.init);
	});

	// Clear gallery
	$('#zip_uploader').on('click', '.clear_gallery', function (e) {
		e.preventDefault();
		e.stopImmediatePropagation();

		var curent_val = $('#term_icon_value').val();
		if ( curent_val !== '' ) {
			var conf = confirm(locals.pixtypes_l18n.confirmClearGallery);
			if ( conf ) {
				$('#term_icon_value').val('');
				zip_uploader_ajax_preview();
			}
		} else {
			alert(locals.pixtypes_l18n.alertGalleryIsEmpty);
		}
	});

})(jQuery);
