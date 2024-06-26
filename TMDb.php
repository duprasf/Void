<?php

namespace Void;

/**
* TMDb PHP API class - API 'themoviedb.org'
* API Documentation: http://help.themoviedb.org/kb/api/
* Documentation and usage in README file
*
* @author Jonas De Smet - Glamorous
* @since 09.11.2009
* @date 16.11.2012
* @copyright Jonas De Smet - Glamorous
* @version 1.5.1
* @license BSD http://www.opensource.org/licenses/bsd-license.php
*/
class TMDb
{
    public const POST = 'post';
    public const GET = 'get';
    public const HEAD = 'head';

    public const IMAGE_BACKDROP = 'backdrop';
    public const IMAGE_POSTER = 'poster';
    public const IMAGE_PROFILE = 'profile';

    public const API_VERSION = '3';
    public const API_URL = 'api.themoviedb.org';
    public const API_SCHEME = 'http://';
    public const API_SCHEME_SSL = 'https://';

    public const VERSION = '1.5.0';

    /**
    * The API-key
    *
    * @var string
    */
    protected $_apikey;

    /**
    * The default language
    *
    * @var string
    */
    protected $_lang;

    /**
    * The TMDb-config
    *
    * @var object
    */
    protected $_config;

    /**
    * Stored Session Id
    *
    * @var string
    */
    protected $_session_id;

    /**
    * API Scheme
    *
    * @var string
    */
    protected $_apischeme;

    /**
    * Default constructor
    *
    * @param string $apikey			API-key recieved from TMDb
    * @param string $defaultLang		Default language (ISO 3166-1)
    * @param boolean $config			Load the TMDb-config
    * @return void
    */
    public function __construct($apikey, $default_lang = 'en', $config = false, $scheme = TMDb::API_SCHEME)
    {
        $this->_apikey = (string) $apikey;
        $this->_apischeme = ($scheme == TMDb::API_SCHEME) ? TMDb::API_SCHEME : TMDb::API_SCHEME_SSL;
        $this->setLang($default_lang);

        if($config === true) {
            $this->getConfiguration();
        }
    }

    /**
    * Search a movie by querystring
    *
    * @param string $text				Query to search after in the TMDb database
    * @param int $page					Number of the page with results (default first page)
    * @param bool $adult				Whether of not to include adult movies in the results (default FALSE)
    * @param mixed $lang				Filter the result with a language (ISO 3166-1) other then default, use FALSE to retrieve results from all languages
    * @return TMDb result array
    */
    public function searchMovie($query, $page = 1, $adult = false, $year = null, $lang = null)
    {
        $params = array(
            'query' => $query,
            'page' => (int) $page,
            'language' => ($lang !== null) ? $lang : $this->getLang(),
            'include_adult' => (bool) $adult,
            'year' => $year,
        );
        return $this->_makeCall('search/movie', $params);
    }

    /**
    * Search a person by querystring
    *
    * @param string $text				Query to search after in the TMDb database
    * @param int $page					Number of the page with results (default first page)
    * @param bool $adult				Whether of not to include adult movies in the results (default FALSE)
    * @return TMDb result array
    */
    public function searchPerson($query, $page = 1, $adult = false)
    {
        $params = array(
            'query' => $query,
            'page' => (int) $page,
            'include_adult' => (bool) $adult,
        );
        return $this->_makeCall('search/person', $params);
    }

    /**
    * Search a company by querystring
    *
    * @param string $text				Query to search after in the TMDb database
    * @param int $page					Number of the page with results (default first page)
    * @return TMDb result array
    */
    public function searchCompany($query, $page = 1)
    {
        $params = array(
            'query' => $query,
            'page' => $page,
        );
        return $this->_makeCall('search/company', $params);
    }

    /**
    * Retrieve information about a collection
    *
    * @param int $id					Id from a collection (retrieved with getMovie)
    * @param mixed $lang				Filter the result with a language (ISO 3166-1) other then default, use FALSE to retrieve results from all languages
    * @return TMDb result array
    */
    public function getCollection($id, $lang = null)
    {
        $params = array(
            'language' => ($lang !== null) ? $lang : $this->getLang(),
        );
        return $this->_makeCall('collection/'.$id, $params);
    }

    /**
    * Retrieve all basic information for a particular movie
    *
    * @param mixed $id					TMDb-id or IMDB-id
    * @param mixed $lang				Filter the result with a language (ISO 3166-1) other then default, use FALSE to retrieve results from all languages
    * @return TMDb result array
    */
    public function getMovie($id, $lang = null)
    {
        $params = array(
            'language' => ($lang !== null) ? $lang : $this->getLang(),
        );
        return $this->_makeCall('movie/'.$id, $params);
    }

    /**
    * Retrieve alternative titles for a particular movie
    *
    * @param mixed $id					TMDb-id or IMDB-id
    * @params string $country			Only include titles for a particular country (ISO 3166-1)
    * @return TMDb result array
    */
    public function getMovieTitles($id, $country = null)
    {
        $params = array(
            'country' => $country,
        );
        return $this->_makeCall('movie/'.$id.'/alternative_titles', $params);
    }

    /**
    * Retrieve all of the movie cast information for a particular movie
    *
    * @param mixed $id					TMDb-id or IMDB-id
    * @return TMDb result array
    */
    public function getMovieCast($id)
    {
        return $this->_makeCall('movie/'.$id.'/casts');
    }

    /**
    * Retrieve all of the keywords for a particular movie
    *
    * @param mixed $id					TMDb-id or IMDB-id
    * @return TMDb result array
    */
    public function getMovieKeywords($id)
    {
        return $this->_makeCall('movie/'.$id.'/keywords');
    }

    /**
    * Retrieve all the release and certification data for a particular movie
    *
    * @param mixed $id					TMDb-id or IMDB-id
    * @return TMDb result array
    */
    public function getMovieReleases($id)
    {
        return $this->_makeCall('movie/'.$id.'/releases');
    }

    /**
    * Retrieve available translations for a particular movie
    *
    * @param mixed $id					TMDb-id or IMDB-id
    * @return TMDb result array
    */
    public function getMovieTranslations($id)
    {
        return $this->_makeCall('movie/'.$id.'/translations');
    }

    /**
    * Retrieve available trailers for a particular movie
    *
    * @param mixed $id					TMDb-id or IMDB-id
    * @param mixed $lang				Filter the result with a language (ISO 3166-1) other then default, use FALSE to retrieve results from all languages
    * @return TMDb result array
    */
    public function getMovieTrailers($id, $lang = null)
    {
        $params = array(
            'language' => ($lang !== null) ? $lang : $this->getLang(),
        );
        return $this->_makeCall('movie/'.$id.'/trailers', $params);
    }

    /**
    * Retrieve all images for a particular movie
    *
    * @param mixed $id					TMDb-id or IMDB-id
    * @param mixed $lang				Filter the result with a language (ISO 3166-1) other then default, use FALSE to retrieve results from all languages
    * @return TMDb result array
    */
    public function getMovieImages($id, $lang = null)
    {
        $params = array(
            'language' => ($lang !== null) ? $lang : $this->getLang(),
        );
        return $this->_makeCall('movie/'.$id.'/images', $params);
    }

    /**
    * Retrieve similar movies for a particular movie
    *
    * @param mixed $id					TMDb-id or IMDB-id
    * @param int $page					Number of the page with results (default first page)
    * @param mixed $lang				Filter the result with a language (ISO 3166-1) other then default, use FALSE to retrieve results from all languages
    * @return TMDb result array
    */
    public function getSimilarMovies($id, $page = 1, $lang = null)
    {
        $params = array(
            'page' => (int) $page,
            'language' => ($lang !== null) ? $lang : $this->getLang(),
        );
        return $this->_makeCall('movie/'.$id.'/similar_movies', $params);
    }

    /**
    * Retrieve newest movie added to TMDb
    *
    * @return TMDb result array
    */
    public function getLatestMovie()
    {
        return $this->_makeCall('movie/latest');
    }

    /**
    * Retrieve movies arriving to theatres within the next few weeks
    *
    * @param int $page					Number of the page with results (default first page)
    * @param mixed $lang				Filter the result with a language (ISO 3166-1) other then default, use FALSE to retrieve results from all languages
    * @return TMDb result array
    */
    public function getUpcomingMovies($page = 1, $lang = null)
    {
        $params = array(
            'page' => (int) $page,
            'language' => ($lang !== null) ? $lang : $this->getLang(),
        );
        return $this->_makeCall('movie/upcoming', $params);
    }

    /**
    * Retrieve movies currently in theatres
    *
    * @param int $page					Number of the page with results (default first page)
    * @param mixed $lang				Filter the result with a language (ISO 3166-1) other then default, use FALSE to retrieve results from all languages
    * @return TMDb result array
    */
    public function getNowPlayingMovies($page = 1, $lang = null)
    {
        $params = array(
            'page' => (int) $page,
            'language' => ($lang !== null) ? $lang : $this->getLang(),
        );
        return $this->_makeCall('movie/now_playing', $params);
    }

    /**
    * Retrieve popular movies (list is updated daily)
    *
    * @param int $page					Number of the page with results (default first page)
    * @param mixed $lang				Filter the result with a language (ISO 3166-1) other then default, use FALSE to retrieve results from all languages
    * @return TMDb result array
    */
    public function getPopularMovies($page = 1, $lang = null)
    {
        $params = array(
            'page' => (int) $page,
            'language' => ($lang !== null) ? $lang : $this->getLang(),
        );
        return $this->_makeCall('movie/popular', $params);
    }

    /**
    * Retrieve top-rated movies
    *
    * @param int $page					Number of the page with results (default first page)
    * @param mixed $lang				Filter the result with a language (ISO 3166-1) other then default, use FALSE to retrieve results from all languages
    * @return TMDb result array
    */
    public function getTopRatedMovies($page = 1, $lang = null)
    {
        $params = array(
            'page' => (int) $page,
            'language' => ($lang !== null) ? $lang : $this->getLang(),
        );
        return $this->_makeCall('movie/top_rated', $params);
    }

    /**
    * Retrieve changes for a particular movie
    *
    * @param mixed $id					TMDb-id or IMDB-id
    * @return TMDb result array
    */
    public function getMovieChanges($id)
    {
        return $this->_makeCall('movie/'.$id.'/changes');
    }

    /**
    * Retrieve all id's from changed movies
    *
    * @param int $page					Number of the page with results (default first page)
    * @param string $start_date		String start date as YYYY-MM-DD
    * @param string $end_date			String end date as YYYY-MM-DD (not inclusive)
    * @return TMDb result array
    */
    public function getChangedMovies($page = 1, $start_date = null, $end_date = null)
    {
        $params = array(
            'page' => (int) $page,
            'start_date' => $start_date,
            'end_date' => $end_date,
        );
        return $this->_makeCall('movie/changes', $params);
    }

    /**
    * Retrieve all basic information for a particular person
    *
    * @param int $id					TMDb person-id
    * @return TMDb result array
    */
    public function getPerson($id)
    {
        return $this->_makeCall('person/'.$id);
    }

    /**
    * Retrieve all cast and crew information for a particular person
    *
    * @param int $id					TMDb person-id
    * @param mixed $lang				Filter the result with a language (ISO 3166-1) other then default, use FALSE to retrieve results from all languages
    * @return TMDb result array
    */
    public function getPersonCredits($id, $lang = null)
    {
        $params = array(
            'language' => ($lang !== null) ? $lang : $this->getLang(),
        );
        return $this->_makeCall('person/'.$id.'/credits', $params);
    }

    /**
    * Retrieve all images for a particular person
    *
    * @param mixed $id					TMDb person-id
    * @return TMDb result array
    */
    public function getPersonImages($id)
    {
        return $this->_makeCall('person/'.$id.'/images');
    }

    /**
    * Retrieve changes for a particular person
    *
    * @param mixed $id					TMDb person-id
    * @return TMDb result array
    */
    public function getPersonChanges($id)
    {
        return $this->_makeCall('person/'.$id.'/changes');
    }

    /**
    * Retrieve all id's from changed persons
    *
    * @param int $page					Number of the page with results (default first page)
    * @param string $start_date		String start date as YYYY-MM-DD
    * @param string $end_date			String end date as YYYY-MM-DD (not inclusive)
    * @return TMDb result array
    */
    public function getChangedPersons($page = 1, $start_date = null, $end_date = null)
    {
        $params = array(
            'page' => (int) $page,
            'start_date' => $start_date,
            'start_date' => $end_date,
        );
        return $this->_makeCall('person/changes', $params);
    }

    /**
    * Retrieve all basic information for a particular production company
    *
    * @param int $id					TMDb company-id
    * @return TMDb result array
    */
    public function getCompany($id)
    {
        return $this->_makeCall('company/'.$id);
    }

    /**
    * Retrieve movies for a particular production company
    *
    * @param int $id					TMDb company-id
    * @param int $page					Number of the page with results (default first page)
    * @param mixed $lang				Filter the result with a language (ISO 3166-1) other then default, use FALSE to retrieve results from all languages
    * @return TMDb result array
    */
    public function getMoviesByCompany($id, $page = 1, $lang = null)
    {
        $params = array(
            'page' => (int) $page,
            'language' => ($lang !== null) ? $lang : $this->getLang(),
        );
        return $this->_makeCall('company/'.$id.'/movies', $params);
    }

    /**
    * Retrieve a list of genres used on TMDb
    *
    * @param mixed $lang				Filter the result with a language (ISO 3166-1) other then default, use FALSE to retrieve results from all languages
    * @return TMDb result array
    */
    public function getGenres($lang = null)
    {
        $params = array(
            'language' => ($lang !== null) ? $lang : $this->getLang(),
        );
        return $this->_makeCall('genre/list', $params);
    }

    /**
    * Retrieve movies for a particular genre
    *
    * @param int $id					TMDb genre-id
    * @param int $page					Number of the page with results (default first page)
    * @param mixed $lang				Filter the result with a language (ISO 3166-1) other then default, use FALSE to retrieve results from all languages
    * @return TMDb result array
    */
    public function getMoviesByGenre($id, $page = 1, $lang = null)
    {
        $params = array(
            'page' => (int) $page,
            'language' => ($lang !== null) ? $lang : $this->getLang(),
        );
        return $this->_makeCall('genre/'.$id.'/movies', $params);
    }

    /**
    * Authentication: retrieve authentication token
    * More information about the authentication process: http://help.themoviedb.org/kb/api/user-authentication
    *
    * @return TMDb result array
    */
    public function getAuthToken()
    {
        $result = $this->_makeCall('authentication/token/new');

        if(! isset($result['request_token'])) {
            if($this->getDebugMode()) {
                throw new Exception\TMDb('No valid request token from TMDb');
            } else {
                return false;
            }
        }

        return $result;
    }

    /**
    * Authentication: retrieve authentication session and set it to the class
    * More information about the authentication process: http://help.themoviedb.org/kb/api/user-authentication
    *
    * @param string $token
    * @return TMDb result array
    */
    public function getAuthSession($token)
    {
        $params = array(
            'request_token' => $token,
        );

        $result = $this->_makeCall('authentication/session/new', $params);

        if(isset($result['session_id'])) {
            $this->setAuthSession($result['session_id']);
        }

        return $result;
    }

    /**
    * Authentication: set retrieved session id in the class for authenticated requests
    * More information about the authentication process: http://help.themoviedb.org/kb/api/user-authentication
    *
    * @param string $session_id
    */
    public function setAuthSession($session_id)
    {
        $this->_session_id = $session_id;
    }

    /**
    * Retrieve basic account information
    *
    * @param string $session_id		Set session_id for the account you want to retrieve information from
    * @return TMDb result array
    */
    public function getAccount($session_id = null)
    {
        $session_id = ($session_id === null) ? $this->_session_id : $session_id;
        return $this->_makeCall('account', null, $session_id);
    }

    /**
    * Retrieve favorite movies for a particular account
    *
    * @param int $account_id			TMDb account-id
    * @param string $session_id		Set session_id for the account you want to retrieve information from
    * @param int $page					Number of the page with results (default first page)
    * @param mixed $lang				Get result in other language then default for this user account (ISO 3166-1)
    * @return TMDb result array
    */
    public function getAccountFavoriteMovies($account_id, $session_id = null, $page = 1, $lang = false)
    {
        $session_id = ($session_id === null) ? $this->_session_id : $session_id;
        $params = array(
            'page' => (int) $page,
            'language' => ($lang !== null) ? $lang : '',
        );
        return $this->_makeCall('account/'.$account_id.'/favorite_movies', $params, $session_id);
    }

    /**
    * Retrieve rated movies for a particular account
    *
    * @param int $account_id			TMDb account-id
    * @param string $session_id		Set session_id for the account you want to retrieve information from
    * @param int $page					Number of the page with results (default first page)
    * @param mixed $lang				Get result in other language then default for this user account (ISO 3166-1)
    * @return TMDb result array
    */
    public function getAccountRatedMovies($account_id, $session_id = null, $page = 1, $lang = false)
    {
        $session_id = ($session_id === null) ? $this->_session_id : $session_id;
        $params = array(
            'page' => (int) $page,
            'language' => ($lang !== null) ? $lang : '',
        );
        return $this->_makeCall('account/'.$account_id.'/rated_movies', $params, $session_id);
    }

    /**
    * Retrieve movies that have been marked in a particular account watchlist
    *
    * @param int $account_id			TMDb account-id
    * @param string $session_id		Set session_id for the account you want to retrieve information from
    * @param int $page					Number of the page with results (default first page)
    * @param mixed $lang				Get result in other language then default for this user account (ISO 3166-1)
    * @return TMDb result array
    */
    public function getAccountWatchlistMovies($account_id, $session_id = null, $page = 1, $lang = false)
    {
        $session_id = ($session_id === null) ? $this->_session_id : $session_id;
        $params = array(
            'page' => (int) $page,
            'language' => ($lang !== null) ? $lang : '',
        );
        return $this->_makeCall('account/'.$account_id.'/movie_watchlist', $params, $session_id);
    }

    /**
    * Add a movie to the account favorite movies
    *
    * @param int $account_id			TMDb account-id
    * @param string $session_id		Set session_id for the account you want to retrieve information from
    * @param int $movie_id				TMDb movie-id
    * @param bool $favorite			Add to favorites or remove from favorites (default TRUE)
    * @return TMDb result array
    */
    public function addFavoriteMovie($account_id, $session_id = null, $movie_id = 0, $favorite = true)
    {
        $session_id = ($session_id === null) ? $this->_session_id : $session_id;
        $params = array(
            'movie_id' => (int) $movie_id,
            'favorite' => (bool) $favorite,
        );
        return $this->_makeCall('account/'.$account_id.'/favorite', $params, $session_id, TMDb::POST);
    }

    /**
    * Add a movie to the account watchlist
    *
    * @param int $account_id			TMDb account-id
    * @param string $session_id		Set session_id for the account you want to retrieve information from
    * @param int $movie_id				TMDb movie-id
    * @param bool $watchlist			Add to watchlist or remove from watchlist (default TRUE)
    * @return TMDb result array
    */
    public function addMovieToWatchlist($account_id, $session_id = null, $movie_id = 0, $watchlist = true)
    {
        $session_id = ($session_id === null) ? $this->_session_id : $session_id;
        $params = array(
            'movie_id' => (int) $movie_id,
            'movie_watchlist' => (bool) $watchlist,
        );
        return $this->_makeCall('account/'.$account_id.'/movie_watchlist', $params, $session_id, TMDb::POST);
    }

    /**
    * Add a rating to a movie
    *
    * @param string $session_id		Set session_id for the account you want to retrieve information from
    * @param int $movie_id				TMDb movie-id
    * @param float $value				Value between 1 and 10
    * @return TMDb result array
    */
    public function addMovieRating($session_id = null, $movie_id = 0, $value = 0)
    {
        $session_id = ($session_id === null) ? $this->_session_id : $session_id;
        $params = array(
            'value' => is_numeric($value) ? floatval($value) : 0,
        );
        return $this->_makeCall('movie/'.$movie_id.'/rating', $params, $session_id, TMDb::POST);
    }

    /**
    * Get configuration from TMDb
    *
    * @return TMDb result array
    */
    public function getConfiguration()
    {
        $config = $this->_makeCall('configuration');

        if(! empty($config)) {
            $this->setConfig($config);
        }

        return $config;
    }

    /**
    * Get Image URL
    *
    * @param string $filepath			Filepath to image
    * @param const $imagetype			Image type: TMDb::IMAGE_BACKDROP, TMDb::IMAGE_POSTER, TMDb::IMAGE_PROFILE
    * @param string $size				Valid size for the image
    * @return string
    */
    public function getImageUrl($filepath, $imagetype, $size)
    {
        $config = $this->getConfig();

        if(isset($config['images'])) {
            $base_url = $config['images']['base_url'];
            $available_sizes = $this->getAvailableImageSizes($imagetype);

            if(in_array($size, $available_sizes)) {
                return $base_url.$size.$filepath;
            } else {
                throw new Exception\TMDb('The size "'.$size.'" is not supported by TMDb');
            }
        } else {
            throw new Exception\TMDb('No configuration available for image URL generation');
        }
    }

    /**
    * Get available image sizes for a particular image type
    *
    * @param const $imagetype			Image type: TMDb::IMAGE_BACKDROP, TMDb::IMAGE_POSTER, TMDb::IMAGE_PROFILE
    * @return array
    */
    public function getAvailableImageSizes($imagetype)
    {
        $config = $this->getConfig();

        if(isset($config['images'][$imagetype.'_sizes'])) {
            return $config['images'][$imagetype.'_sizes'];
        } else {
            throw new Exception\TMDb('No configuration available to retrieve available image sizes');
        }
    }

    /**
    * Get ETag to keep track of state of the content
    *
    * @param string $uri				Use an URI to know the version of it. For example: 'movie/550'
    * @return string
    */
    public function getVersion($uri)
    {
        $headers = $this->_makeCall($uri, null, null, TMDb::HEAD);
        return isset($headers['Etag']) ? $headers['Etag'] : '';
    }

    /**
    * Makes the call to the API
    *
    * @param string $function			API specific function name for in the URL
    * @param array $params				Unencoded parameters for in the URL
    * @param string $session_id		Session_id for authentication to the API for specific API methods
    * @param const $method				TMDb::GET or TMDb:POST (default TMDb::GET)
    * @return TMDb result array
    */
    private function _makeCall($function, $params = null, $session_id = null, $method = TMDb::GET)
    {
        $params = (! is_array($params)) ? array() : $params;
        $auth_array = array('api_key' => $this->_apikey);

        if($session_id !== null) {
            $auth_array['session_id'] = $session_id;
        }

        $url = $this->_apischeme.TMDb::API_URL.'/'.TMDb::API_VERSION.'/'.$function.'?'.http_build_query($auth_array, '', '&');

        if($method === TMDb::GET) {
            if(isset($params['language']) and $params['language'] === false) {
                unset($params['language']);
            }

            $url .= (! empty($params)) ? '&'.http_build_query($params, '', '&') : '';
        }

        $results = '{}';

        if (extension_loaded('curl')) {
            $headers = array(
                'Accept: application/json',
            );

            $ch = curl_init();

            if($method == TMDB::POST) {
                $json_string = json_encode($params);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json_string);
                $headers[] = 'Content-Type: application/json';
                $headers[] = 'Content-Length: '.strlen($json_string);
            } elseif($method == TMDb::HEAD) {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
                curl_setopt($ch, CURLOPT_NOBODY, 1);
            }

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            $response = curl_exec($ch);

            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $error_number = curl_errno($ch);
            $error_message = curl_error($ch);

            if($error_number > 0) {
                throw new Exception\TMDb('Method failed: '.$function.' - '.$error_message);
            }

            curl_close($ch);
        } else {
            throw new Exception\TMDb('CURL-extension not loaded');
        }

        $results = json_decode($body, true);

        if(strpos($function, 'authentication/token/new') !== false) {
            $parsed_headers = $this->_http_parse_headers($header);
            $results['Authentication-Callback'] = $parsed_headers['Authentication-Callback'];
        }

        if($results !== null) {
            return $results;
        } elseif($method == TMDb::HEAD) {
            return $this->_http_parse_headers($header);
        } else {
            throw new Exception\TMDb('Server error on "'.$url.'": '.$response);
        }
    }

    /**
    * Setter for the default language
    *
    * @param string $lang		(ISO 3166-1)
    * @return void
    */
    public function setLang($lang)
    {
        $this->_lang = $lang;
    }

    /**
    * Setter for the TMDB-config
    *
    * $param array $config
    * @return void
    */
    public function setConfig($config)
    {
        $this->_config = $config;
    }

    /**
    * Getter for the default language
    *
    * @return string
    */
    public function getLang()
    {
        return $this->_lang;
    }

    /**
    * Getter for the TMDB-config
    *
    * @return array
    */
    public function getConfig()
    {
        if(empty($this->_config)) {
            $this->_config = $this->getConfiguration();
        }

        return $this->_config;
    }

    /*
    * Internal function to parse HTTP headers because of lack of PECL extension installed by many
    *
    * @param string $header
    * @return array
    */
    protected function _http_parse_headers($header)
    {
        $return = array();
        $fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
        foreach($fields as $field) {
            if(preg_match('/([^:]+): (.+)/m', $field, $match)) {
                $match[1] = preg_replace('/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', strtolower(trim($match[1])));
                if(isset($return[$match[1]])) {
                    $return[$match[1]] = array($return[$match[1]], $match[2]);
                } else {
                    $return[$match[1]] = trim($match[2]);
                }
            }
        }
        return $return;
    }
}
