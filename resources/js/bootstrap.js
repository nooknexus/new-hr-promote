import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Import Bootstrap bundle with jQuery
import 'bootstrap';

// You can also import specific Bootstrap components if needed
// import 'bootstrap/js/dist/modal';
// import 'bootstrap/js/dist/dropdown';
