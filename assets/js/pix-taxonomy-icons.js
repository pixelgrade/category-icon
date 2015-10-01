(function ($) {
	$(window).load(function () {

		$(document).on('click', '.open_term_icon_upload', function (e) {
			e.preventDefault();
			wp.media.EditTermIconUpload.frame().open();
		});

		$(document).on('click', '.open_term_icon_delete', function (e) {
			e.preventDefault();
			$('#term_icon_value' ).val('');

			$(this).siblings('img').remove();

			$(this).remove();
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
						current_selection = controller.get('selection' ),
						selected_models = current_selection.models;

					if ( typeof selected_models[0] !== "undefined" ) {
						// set value
						var new_id = selected_models[0].id,
							$input = $('#term_icon_value' ),
							this_container = $input.parent();

						$input.val( new_id );
						// preview the new value
						if ( typeof selected_models[0].attributes !== undefined && typeof selected_models[0].attributes.sizes !== 'undefined' && typeof selected_models[0].attributes.sizes.thumbnail !== 'undefined' && typeof selected_models[0].attributes.sizes.thumbnail.url !== "undefined" ) {
							var thumb_url = selected_models[0].attributes.sizes.thumbnail.url;
							var $current_img = $(this_container).find('img');
							if ( $current_img.length > 0 ) {
								$current_img.attr('src', thumb_url);
							} else {
								var new_img = $('<img>');
								new_img.attr('src', thumb_url);
								$(this_container).append( new_img );
								$(this_container).append( '<span class="open_term_icon_delete button button-secondary">Delete</span>' );
							}
						}
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
					multiple: false
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

})(jQuery);
