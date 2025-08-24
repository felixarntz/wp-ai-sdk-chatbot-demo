/**
 * WordPress dependencies
 */
import domReady from '@wordpress/dom-ready';
import { createRoot, render } from '@wordpress/element';

/**
 * Internal dependencies
 */
import ChatbotApp from './components/ChatbotApp';
import type { ServerChatbotConfig } from './types';

/**
 * Mounts the given component into the DOM.
 *
 * @since 0.1.0
 *
 * @param jsx          - The JSX node to be mounted.
 * @param renderTarget - The target element to render the JSX into.
 */
function mountApp( jsx: JSX.Element, renderTarget: Element ) {
	if ( createRoot ) {
		const root = createRoot( renderTarget );
		root.render( jsx );
	} else {
		render( jsx, renderTarget );
	}
}

/**
 * Initializes the app by loading the chatbot when the DOM is ready.
 *
 * @since 0.1.0
 *
 * @param config - The chatbot configuration from the server.
 */
export function loadChatbot( config: ServerChatbotConfig ) {
	domReady( () => {
		const renderTarget = document.getElementById(
			'wp-ai-sdk-chatbot-demo-root'
		);
		if ( ! renderTarget ) {
			return;
		}

		mountApp( <ChatbotApp serverChatbotConfig={ config } />, renderTarget );
	} );
}
