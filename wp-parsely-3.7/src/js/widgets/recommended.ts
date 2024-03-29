/**
 * External dependencies
 */
import domReady from '@wordpress/dom-ready';

/**
 * Internal dependencies
 */
import { getUuidFromVisitorCookie } from '../lib/personalization';

interface WidgetData {
	data: {
		[key: string]: WidgetRecommendation;
	};
}

interface WidgetRecommendation {
	title: string;
	url: string;
	author: string;
	image_url: string;
	thumb_url_medium: string;
}

interface WidgetOptions {
	url: string;
	outerDiv: Element;
	displayAuthor: boolean;
	displayDirection: string | null;
	imgDisplay: string | null;
	widgetId: string | null;
}

interface WidgetOptionsGroup {
	[key: string]: WidgetOptions[];
}

function constructUrl( apiUrl: string, permalink: string, personalized: boolean ): string {
	if ( personalized ) {
		const uuid = getUuidFromVisitorCookie();
		if ( uuid ) {
			return `${ apiUrl }&uuid=${ encodeURIComponent( uuid ) }`;
		}
	}

	return `${ apiUrl }&url=${ encodeURIComponent( permalink ) }`;
}

function constructWidget( widget: Element ): WidgetOptions {
	const apiUrl = widget.getAttribute( 'data-parsely-widget-api-url' ) || '';
	const permalink = widget.getAttribute( 'data-parsely-widget-permalink' ) || '';
	const personalized = widget.getAttribute( 'data-parsely-widget-personalized' ) === 'true';
	const url = constructUrl( apiUrl, permalink, personalized );

	return {
		outerDiv: widget,
		url,
		displayAuthor: widget.getAttribute( 'data-parsely-widget-display-author' ) === 'true',
		displayDirection: widget.getAttribute( 'data-parsely-widget-display-direction' ),
		imgDisplay: widget.getAttribute( 'data-parsely-widget-img-display' ),
		widgetId: widget.getAttribute( 'data-parsely-widget-id' ),
	};
}

function renderWidget( data: WidgetData, {
	outerDiv,
	displayAuthor,
	displayDirection,
	imgDisplay,
	widgetId,
}: WidgetOptions ) {
	if ( imgDisplay !== 'none' ) {
		outerDiv.classList.add( 'display-thumbnail' );
	}

	if ( displayDirection ) {
		outerDiv.classList.add( 'list-' + displayDirection );
	}

	const outerList = document.createElement( 'ul' );
	outerList.className = 'parsely-recommended-widget';
	outerDiv.appendChild( outerList );

	for ( const [ key, value ] of Object.entries( data.data ) ) {
		const widgetEntry = document.createElement( 'li' );
		widgetEntry.className = 'parsely-recommended-widget-entry';
		widgetEntry.setAttribute( 'id', 'parsely-recommended-widget-item' + key );

		const textDiv = document.createElement( 'div' );
		textDiv.className = 'parsely-text-wrapper';

		const thumbnailImg = document.createElement( 'img' );
		if ( imgDisplay === 'parsely_thumb' ) {
			thumbnailImg.setAttribute( 'src', value.thumb_url_medium );
		} else if ( imgDisplay === 'original' ) {
			thumbnailImg.setAttribute( 'src', value.image_url );
		}
		widgetEntry.appendChild( thumbnailImg );

		const itmId = `?itm_campaign=${ widgetId }`;
		const itmMedium = '&itmMedium=site_widget';
		const itmSource = '&itmSource=parsely_recommended_widget';
		const itmContent = '&itm_content=widget_item-' + key;
		const itmLink = value.url + itmId + itmMedium + itmSource + itmContent;

		const postTitle = document.createElement( 'div' );
		postTitle.className = 'parsely-recommended-widget-title';

		const postLink = document.createElement( 'a' );
		postLink.setAttribute( 'href', itmLink );
		postLink.textContent = value.title;

		postTitle.appendChild( postLink );
		textDiv.appendChild( postTitle );

		if ( displayAuthor ) {
			const authorLink = document.createElement( 'div' );
			authorLink.className = 'parsely-recommended-widget-author';
			authorLink.textContent = value.author;

			textDiv.appendChild( authorLink );
		}

		widgetEntry.appendChild( textDiv );
		outerList.appendChild( widgetEntry );
	}

	outerDiv.appendChild( outerList );
	outerDiv.closest( '.widget.Recommended_Widget' )?.classList.remove( 'parsely-recommended-widget-hidden' );
}

domReady( () => {
	const widgetDOMElements = document.querySelectorAll( '.parsely-recommended-widget' );
	const widgetObjects = Array.from( widgetDOMElements ).map( ( widget: Element ) => constructWidget( widget ) );

	const widgetsGroupedByUrl: WidgetOptionsGroup = widgetObjects.reduce( ( acc: WidgetOptionsGroup, curr: WidgetOptions ): object => {
		if ( ! acc[ curr.url ] ) {
			acc[ curr.url ] = [];
		}
		acc[ curr.url ].push( curr );
		return acc;
	}, {} );

	Object.entries( widgetsGroupedByUrl ).forEach( ( [ url, widgets ] ) => {
		fetch( url )
			.then( ( response ) => response.json() )
			.then( ( data ) => {
				widgets.forEach( ( widget: WidgetOptions ) => {
					renderWidget( data, widget );
				} );
			} );
	} );
} );
