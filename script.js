function translateElements(file_prefix, language_code){
    let url = "./"+file_prefix+"_translations.json";
    fetch(url)
        .then((response) => {
            return response.json();
        })
        .then((myJson) => {
            myJson.forEach( def => {
                let element = document.getElementById(def.id);
                if(def[language_code].hidden) {
                    if (element !== null) {
                        element.value = def[language_code].hidden;
                    } else {
                        element = document.createElement("input");
                        element.type = "hidden";
                        element.id = def.id;
                        element.value = def[language_code].hidden;
                        document.body.appendChild(element);
                    }
                }
                if(element !== null) {
                    if(def[language_code].text) {
                        element.textContent = def[language_code].text;
                    }
                    if(def[language_code].placeholder) {
                        element.placeholder = def[language_code].placeholder;
                    }
                    if(def[language_code].value) {
                        element.value = def[language_code].value;
                    }
                    if(def[language_code].href) {
                        element.href = def[language_code].href;
                    }
                    if(def[language_code].src) {
                        element.src = def[language_code].src;
                    }
                    if(def[language_code].alt) {
                        element.alt = def[language_code].alt;
                    }
                    if(def[language_code].title) {
                        element.title = def[language_code].title;
                    }
                    if(def[language_code].innerHTML) {
                        element.innerHTML = def[language_code].innerHTML;
                    }
                }
                else{
                    console.log(def.id);
                }
            });
        });
}

function getURLParameter(name) {
    return new URLSearchParams(window.location.search).get(name);
}