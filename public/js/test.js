const search_api_suggests_url = 'http://localhost:8000/search';

const search_form = document.querySelector('form');
const search_input = document.querySelector('.search-input');
const suggestions_container = document.querySelector('.suggestions');

const suggestions_event = document.getElementsByClassName('suggest-item');

const suggestItemClass = 'suggest-item';
const HighlightedSuggestItemClass = 'suggest-item-highlighted';


search_input.addEventListener('input', debounce(showSuggestions, 200) );


document.addEventListener('click', (event) => {

    const target = event.target;
    if (target.classList.contains(suggestItemClass)) {
        event.preventDefault();
        handleSuggestionSelect(target)
    }
    if (target.classList.contains(HighlightedSuggestItemClass)) {
        event.preventDefault();
        handleHighlightedSuggestionSelect(target)
    }
});


async function showSuggestions() {

    clearSuggests();

    const search_value = search_input.value;
    const suggests_response = await getSuggests( search_value );
    const suggests = JSON.parse(await suggests_response.text());

    // const suggests = JSON.parse(await suggests_response.text()).suggests;

    clearSuggests();

    if (suggests.length !== 0) {
        if (suggests[0].options) {
            suggests[0].options.forEach( suggest => {
                let suggest_div = document.createElement("div");
                let suggest_HTML = document.createElement("span");
                let suggest_highlight = document.createElement("b");

                suggest_div.classList.add('suggest-div');

                    // suggest_HTML.setAttribute('href', '');
                suggest_HTML.classList.add(suggestItemClass);
                suggest_highlight.classList.add(HighlightedSuggestItemClass);
                    
                split = suggest.highlighted.split('<highlight>');

                suggest_highlight.innerText = split[1]; //suggest.text;
                suggest_HTML.innerText = split[0]; //suggest.text;

                suggest_HTML.addEventListener('click', function() { document.getElementById('suggest').innerHTML = ""; })
                    
                suggest_HTML.appendChild(suggest_highlight);

                suggest_div.appendChild(suggest_HTML);
                suggestions_container.appendChild(suggest_div);
            });
        }

        suggestions_container.style.display = 'block';
        

    }

}

function clearSuggests(){
    suggestions_container.style.display = 'none';
    suggestions_container.replaceChildren();
}

function handleSuggestionSelect(suggestionElement) {
    search_input.value = suggestionElement.innerText;
    // search_form.submit();
}

function handleHighlightedSuggestionSelect(suggestionElement) {
    search_input.value = suggestionElement.parentElement.innerText;
}

async function getSuggests(text) {
    return await fetch(search_api_suggests_url, {
        method: 'POST',
        cache: 'no-cache',
        redirect: 'follow',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            'search': text
        })
    })
}

function debounce(func, wait = 500) {
    let timer;

    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => func.apply(this, args), wait)
    }
}