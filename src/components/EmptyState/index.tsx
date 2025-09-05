/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './style.scss';

type EmptyStateProps = {
	userName: string;
};

/**
 * Renders a custom empty state that welcomes the user by name.
 *
 * @since 0.1.0
 *
 * @param props - Component props.
 * @returns The component to be rendered.
 */
export default function EmptyState( props: EmptyStateProps ) {
	const { userName } = props;

	return (
		<div className="wp-ai-sdk-chatbot-demo__empty-state">
			<div className="wp-ai-sdk-chatbot-demo__empty-state-content">
				<h3 className="wp-ai-sdk-chatbot-demo__empty-state-title">
					{userName
						? __(`Welcome, ${userName}!`, 'wp-ai-sdk-chatbot-demo')
						: __('Welcome!', 'wp-ai-sdk-chatbot-demo')}
				</h3>
				<p className="wp-ai-sdk-chatbot-demo__empty-state-message">
					{__('I\'m here to help you with any questions about your WordPress site.', 'wp-ai-sdk-chatbot-demo')}
				</p>
			</div>
		</div>
	);
}