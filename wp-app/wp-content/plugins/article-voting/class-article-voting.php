<?php
/**
 * Plugin Name: Article Voting
 * Description: Allows website visitors to vote on articles.
 * Version: 1.0
 * Author: Nikola Dikic
 *
 * @package Article_Voting
 * @version 1.0
 */
class Article_Voting {
	/**
	 * Initializes the Article Voting Plugin.
	 *
	 * This constructor method registers necessary actions and filters for the plugin to function.
	 * It enqueues necessary scripts and stylesheets, sets up AJAX endpoints, and adds voting buttons to single post content.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		wp_enqueue_style( 'article-voting-style', plugin_dir_url( __FILE__ ) . 'css/article-voting.css', array(), '1.0', 'all' );
		add_action( 'wp_ajax_submit_vote', array( $this, 'submit_vote' ) );
		add_action( 'wp_ajax_nopriv_submit_vote', array( $this, 'submit_vote' ) );
		add_filter( 'the_content', array( $this, 'add_voting_buttons' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
	}

	/**
	 * Enqueue scripts
	 *
	 * @return void
	 * */
	public function enqueue_scripts() {
		wp_enqueue_script( 'article-voting-script', plugin_dir_url( __FILE__ ) . 'js/article-voting.js', array( 'jquery' ), '1.0', true );
		wp_localize_script( 'article-voting-script', 'article_voting_ajax', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
	}

	/**
	 * Add voting buttons to the end of single post content
	 *
	 * @param string $content Content container.
	 * @return string
	 */
	public function add_voting_buttons( $content ) {
		if ( is_single() ) {
			global $post;
			$nonce    = wp_create_nonce( 'article_voting_nonce' );
			$content .= '<div class="article-voting" data-post-id="' . $post->ID . '" data-nonce="' . $nonce . '">';

			// Check if the visitor has voted for this article.
			if ( isset( $_SERVER['REMOTE_ADDR'] ) && isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
				$remote_addr         = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
				$http_user_agent     = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
				$visitor_fingerprint = md5( $remote_addr . $http_user_agent );
			}
			$voted_articles = get_post_meta( $post->ID, 'article_voting_voted_articles', true );

			if ( isset( $visitor_fingerprint ) && isset( $voted_articles[ $visitor_fingerprint ] ) ) {
				$vote = $voted_articles[ $visitor_fingerprint ];

				// If visitor has voted, display thank you message and voting results.
				$positive_count      = $this->get_positive_count( $post->ID );
				$negative_count      = $this->get_negative_count( $post->ID );
				$total_votes         = $positive_count + $negative_count;
				$positive_percentage = ( $total_votes > 0 ) ? round( ( $positive_count / $total_votes ) * 100 ) : 0;
				$negative_percentage = ( $total_votes > 0 ) ? round( ( $negative_count / $total_votes ) * 100 ) : 0;

				$content .= '<p>THANK YOU FOR YOUR FEEDBACK!</p>';
				if ( 'positive' === $vote ) {
					$content .= '<div class="vote-result active">';
				} else {
					$content .= '<div class="vote-result">';
				}
				$content .= file_get_contents( plugin_dir_url( __FILE__ ) . 'img/smiley-happy.svg' );
				$content .= '<span>' . $positive_percentage . '%</span>';
				$content .= '</div>';
				if ( 'negative' === $vote ) {
					$content .= '<div class="vote-result active">';
				} else {
					$content .= '<div class="vote-result">';
				}
				$content .= file_get_contents( plugin_dir_url( __FILE__ ) . 'img/smiley-sad.svg' );
				$content .= '<span>' . $negative_percentage . '%</span>';
				$content .= '</div>';
			} else {
				// If visitor hasn't voted, display voting options.
				$content .= '<p>WAS THIS ARTICLE HELPFULL?</p>';
				$content .= '<button class="vote-button" data-vote="positive" data-nonce="' . $nonce . '">';
				$content .= file_get_contents( plugin_dir_url( __FILE__ ) . 'img/smiley-happy.svg' );
				$content .= '<span>YES</span>';
				$content .= '</button>';
				$content .= '<button class="vote-button" data-vote="negative" data-nonce="' . $nonce . '">';
				$content .= file_get_contents( plugin_dir_url( __FILE__ ) . 'img/smiley-sad.svg' );
				$content .= '<span>NO</span>';
				$content .= '</button>';
			}

			$content .= '</div>';
		}
		return $content;
	}

	/**
	 * Submit vote via Ajax.
	 */
	public function submit_vote() {
		check_ajax_referer( 'article_voting_nonce', 'nonce' );

		if ( isset( $_POST['vote'], $_POST['post_id'], $_POST['nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'article_voting_nonce' ) ) {
			$vote    = sanitize_text_field( wp_unslash( $_POST['vote'] ) );
			$post_id = intval( $_POST['post_id'] );

			if ( isset( $_SERVER['REMOTE_ADDR'] ) && isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
				$remote_addr         = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
				$http_user_agent     = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
				$visitor_fingerprint = md5( $remote_addr . $http_user_agent );
			}

			$voted_articles = get_post_meta( $post_id, 'article_voting_voted_articles', true );
			if ( ! $voted_articles ) {
				$voted_articles = array();
			}
			if ( isset( $visitor_fingerprint ) && isset( $voted_articles[ $visitor_fingerprint ] ) ) {
				wp_send_json_error( 'You have already voted for this article.' );
			}

			// Add visitor fingerprint to the list of voted articles.
			$voted_articles[ $visitor_fingerprint ] = $vote;
			update_post_meta( $post_id, 'article_voting_voted_articles', $voted_articles );

			// Increment positive or negative count based on the vote.
			if ( 'positive' === $vote ) {
				$positive_count = $this->get_positive_count( $post_id );
			} elseif ( 'negative' === $vote ) {
				$negative_count = $this->get_negative_count( $post_id );
			}

			// Get updated counts.
			$positive_count = $this->get_positive_count( $post_id );
			$negative_count = $this->get_negative_count( $post_id );
			$total_votes    = $positive_count + $negative_count;

			$positive_percentage = round( ( $positive_count / $total_votes ) * 100 );
			$negative_percentage = round( ( $negative_count / $total_votes ) * 100 );

			$content = '<p>THANK YOU FOR YOUR FEEDBACK!</p>';
			if ( 'positive' === $vote ) {
				$content .= '<div class="vote-result active">';
			} else {
				$content .= '<div class="vote-result">';
			}
			$content .= file_get_contents( plugin_dir_url( __FILE__ ) . 'img/smiley-happy.svg' );
			$content .= '<span>' . $positive_percentage . '%</span>';
			$content .= '</div>';
			if ( 'negative' === $vote ) {
				$content .= '<div class="vote-result active">';
			} else {
				$content .= '<div class="vote-result">';
			}
			$content .= file_get_contents( plugin_dir_url( __FILE__ ) . 'img/smiley-sad.svg' );
			$content .= '<span>' . $negative_percentage . '%</span>';
			$content .= '</div>';

			wp_send_json_success( $content );
		}
		wp_die();
	}

	/**
	 * Function to get positive vote count
	 *
	 * @param int $post_id Id of the post.
	 */
	private function get_positive_count( $post_id ) {
		$voted_articles = get_post_meta( $post_id, 'article_voting_voted_articles', true );
		$positive_count = array_count_values( $voted_articles )['positive'];
		return intval( $positive_count );
	}

	/**
	 * Function to get negative vote count
	 *
	 * @param int $post_id Id of the post.
	 */
	private function get_negative_count( $post_id ) {
		$voted_articles = get_post_meta( $post_id, 'article_voting_voted_articles', true );
		$negative_count = array_count_values( $voted_articles )['negative'];
		return intval( $negative_count );
	}

	/**
	 * Function to add meta box for backend
	 */
	public function add_meta_box() {
		add_meta_box( 'article-voting-meta-box', 'Article Voting Results', array( $this, 'render_meta_box' ), 'post', 'side', 'default' );
	}

	/**
	 * Render meta box content.
	 *
	 * @param object $post Post object.
	 */
	public function render_meta_box( $post ) {
		$positive_count = $this->get_positive_count( $post->ID );
		$negative_count = $this->get_negative_count( $post->ID );
		echo '<p><strong>Positive Votes:</strong> ' . esc_attr( $positive_count ) . '</p>';
		echo '<p><strong>Negative Votes:</strong> ' . esc_attr( $negative_count ) . '</p>';
	}
}

// Instantiate the plugin.
$article_voting = new Article_Voting();
