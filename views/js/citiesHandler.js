$(document).ready(() => {
    bindCityNomenclature();

    $(document).on('ajaxComplete', (event, xhr, settings) => {
        if (settings.url.includes("addressForm")) {
            bindCityNomenclature();
            updateCityFieldFromCurrentState();
        }
    });
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

const getFormElements = () => ({
    country: $(getFieldByName('country')),
    state: $(getFieldByName('state')),
    city: $(getFieldByName('city')),
});

const bindCityNomenclature = () => {
    const formElements = getFormElements();
    if (formElements.state.length === 0 || formElements.city.length === 0 || formElements.country.length === 0) {
        return;
    }

    $(document).off('change.samedayCities', `#${formElements.state[0].id}`);
    $(document).on('change.samedayCities', `#${formElements.state[0].id}`, (event) => {
        updateCities(formElements.city[0], event.target.value, document.getElementById(formElements.country[0].id).value);
    });
};

const updateCityFieldFromCurrentState = () => {
    const formElements = getFormElements();
    if (formElements.state.length === 0 || formElements.city.length === 0 || formElements.country.length === 0) {
        return;
    }

    updateCities(formElements.city[0], formElements.state.val(), formElements.country.val());
};

const updateCities = (cityField, stateCode, countryCode) => {
    if (!cityField || !stateCode || !countryCode) {
        return;
    }

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
