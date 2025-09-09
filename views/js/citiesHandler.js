$(document).ready(() => {
    $(document).on('ajaxComplete', (event, xhr, settings) => {
        if (settings.url.includes("addressForm")) {
            if (formElements.state.length > 0) {
                $(document).off('change', `#${formElements.state[0].id}`);
                $(document).on('change', `#${formElements.state[0].id}`, (event) => {
                    updateCities(
                        document.getElementById(formElements.city[0].id),
                        event.target.value,
                        document.getElementById(formElements.country[0].id).value
                    );
                });
            }
        }
    });

    if (undefined !== formElements.state && formElements.state.length > 0) {
        formElements.state.on('change', (event) => {
            updateCities(formElements.city[0], event.target.value, formElements.country.val());
        });
    }
});

/**
 * @param fieldName
 *
 * @returns HTML|undefined
 */
const getFieldByName = (fieldName) => {
    return Array.from(document.querySelectorAll('input, select'))
        .find(element => element.id.includes(fieldName)
    );
}

let citySelectElement;

let formElements = {
    country: $(getFieldByName('country')),
    state: $(getFieldByName('state')),
    city: $(getFieldByName('city')),
};

const updateCities = (cityField, stateCode, countryCode) => {
    let cities = SamedayCities?.[countryCode]?.[stateCode] ?? [];
    if (cities.length > 0) {
        if (undefined !== citySelectElement && citySelectElement.length > 0) {
            populateCityField(cities, citySelectElement, cityField);
        } else {
            citySelectElement = document.createElement("select");
            citySelectElement.setAttribute("id", cityField.getAttribute('id'));
            citySelectElement.setAttribute("name", 'city');
            citySelectElement.setAttribute("class", "form-control form-control-select");

            populateCityField(cities, citySelectElement, cityField);
        }
    } else {
        if (undefined !== citySelectElement && citySelectElement.length > 0) {
            citySelectElement.replaceWith(cityField);
        }
    }
}

const createOptionElement = (value, text, cityFieldValue = null) => {
    const option = document.createElement('option');
    option.value = value;
    option.setAttribute('data-alternate-values', `[${value}]`);
    if (value === cityFieldValue) {
        option.setAttribute('selected', true);
    }
    option.textContent = text;

    return option;
}

const populateCityField = (cities, citySelectElement, cityField) => {
    citySelectElement.textContent = "";
    citySelectElement.appendChild(createOptionElement("", "Choose a city"));
    cities.forEach((city) => {
        citySelectElement.appendChild(createOptionElement(city.name, city.name, cityField.value));
    });

    cityField.replaceWith(citySelectElement);
}
