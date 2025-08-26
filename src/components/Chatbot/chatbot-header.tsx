/**
 * Internal dependencies
 */
import { useChatbotConfig } from '../../config';
import apiFetch from '@wordpress/api-fetch';

type ChatbotHeaderProps = {
	/**
	 * The route to use for fetching and sending messages.
	 */
	messagesRoute: string;

	/**
	 * Function to call when the history of messages is reset.
	 */
	onReset?: () => void;

	/**
	 * Function to call when the close button is clicked.
	 */
	onClose: () => void;
};

/**
 * Renders the chatbot header.
 *
 * @since 0.1.0
 *
 * @param props - Component props.
 * @returns The component to be rendered.
 */
export default function ChatbotHeader( props: ChatbotHeaderProps ) {
	const { messagesRoute, onReset, onClose } = props;

	const labels = useChatbotConfig( 'labels' );
	if ( ! labels ) {
		return null;
	}

	const handleReset = async () => {
		try {
			await apiFetch( {
				path: messagesRoute,
				method: 'DELETE',
			} );
			if ( onReset ) {
				onReset();
			}
		} catch ( error ) {
			// eslint-disable-next-line no-console
			console.error( error );
		}
	};

	return (
		<div className="wp-ai-sdk-chatbot-demo__header">
			<div className="wp-ai-sdk-chatbot-demo__header-title">
				{ labels.title }
				{ !! labels.subtitle && (
					<div className="wp-ai-sdk-chatbot-demo__header-title__note">
						{ labels.subtitle }
					</div>
				) }
			</div>
			<div className="wp-ai-sdk-chatbot-demo__header-actions">
				<button
					className="wp-ai-sdk-chatbot-demo__header-reset-button"
					aria-label={ labels.resetButton }
					onClick={ handleReset }
				>
					<span className="wp-ai-sdk-chatbot-demo__header-reset-button__icon" />
					<span className="screen-reader-text">
						{ labels.resetButton }
					</span>
				</button>
				<button
					className="wp-ai-sdk-chatbot-demo__header-close-button"
					aria-label={ labels.closeButton }
					onClick={ onClose }
				>
					<span className="wp-ai-sdk-chatbot-demo__header-close-button__icon" />
					<span className="screen-reader-text">
						{ labels.closeButton }
					</span>
				</button>
			</div>
		</div>
	);
}
