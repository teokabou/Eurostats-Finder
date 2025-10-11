function renderTree(data, container) {
    const ul = document.createElement('ul');

    data.forEach(item => {
        const li = document.createElement('li');

        const span = document.createElement('span');
        span.textContent = item.title + (item.code ? ` [${item.code}]` : '');
        span.classList.add('tree-title');

        li.appendChild(span);

        let childContainer = null;
        if (item.children?.length) {
            childContainer = document.createElement('div');
            childContainer.classList.add('child-container');
            childContainer.style.display = 'none';

            renderTree(item.children, childContainer);
            li.appendChild(childContainer);
        }

        let datasetContainer = null;
        if (item.datasets?.length) {
            datasetContainer = document.createElement('ul');
            datasetContainer.classList.add('dataset-container');
            datasetContainer.style.display = 'none';

            item.datasets.forEach(ds => {
                const dsItem = document.createElement('li');
                dsItem.textContent = ds.title + (ds.code ? ` [${ds.code}]` : '');
                dsItem.classList.add('dataset-item');
                dsItem.dataset.code = ds.code;

                dsItem.addEventListener('click', () => {
                    document.querySelectorAll('li.dataset-item').forEach(el => {
                        el.classList.remove('selected');
                        el.dataset.selected = 'false';
                    });
                    dsItem.classList.add('selected');
                    dsItem.dataset.selected = 'true';
                });

                datasetContainer.appendChild(dsItem);
            });

            li.appendChild(datasetContainer);
        }

        span.addEventListener('click', () => {
            if (childContainer) {
                childContainer.style.display = childContainer.style.display === 'none' ? 'block' : 'none';
            }
            if (datasetContainer) {
                datasetContainer.style.display = datasetContainer.style.display === 'none' ? 'block' : 'none';
            }
        });

        ul.appendChild(li);
    });

    container.appendChild(ul);
}

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('tree-container');
    container.textContent = 'Loading...';

    fetch('toc_loader.php')
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            container.innerHTML = '';
            renderTree(data, container);

        })
        .catch(error => {
            container.textContent = 'Failed to load data: ' + error.message;
        });
});

// jQuery φορτωτής
$(function () {
    $('#pricing03-1').on('click', '#loadButton', startSearch);
    $('#pricing03-1').on('click', '#filterButton', applyFilters);
});



function startSearch() {
    const selected = document.querySelector("li.dataset-item.selected");
    if (selected) {
        const code = selected.dataset.code;
        fetchStructureForDataset(code);

        // αφαίρεσε προηγούμενες ενεργές καταστάσεις
        document.querySelectorAll("li.dataset-item").forEach(el => el.classList.remove("active"));

        // πρόσθεσε την κλάση στο επιλεγμένο
        selected.classList.add("active");
    } else {
        alert('Please choose a dataset.');
    }
}


function fetchStructureForDataset(code) {
    const dataflowUrl = `https://ec.europa.eu/eurostat/api/dissemination/sdmx/3.0/structure/dataflow/ESTAT/${code}`;
    const proxied = "fetch_structure.php?url=" + encodeURIComponent(dataflowUrl);

    $.get(proxied, function (dataflowXml) {
        const xmlText = new XMLSerializer().serializeToString(dataflowXml);
        const parser = new DOMParser();
        const xmlDoc = parser.parseFromString(xmlText, "application/xml");

        const structureNode = xmlDoc.getElementsByTagNameNS("*", "Structure")[0];
        const urn = structureNode ? structureNode.textContent : null;
        const match = urn.match(/DataStructure=ESTAT:([^()]+)/);
        if (!match) {
            console.error("Could not extract DSD ID from URN:", urn);
            return;
        }
        const dsdId = match[1];
        const dsdUrl = `https://ec.europa.eu/eurostat/api/dissemination/sdmx/3.0/structure/datastructure/ESTAT/${dsdId}`;
        const proxiedDsd = "fetch_structure.php?url=" + encodeURIComponent(dsdUrl);

        // === ΝΕΟ === Πρώτα πάρε το statistics JSON για να βρεις τις ενεργές τιμές
        const statsUrl = `https://ec.europa.eu/eurostat/api/dissemination/statistics/1.0/data/${code}?format=JSON&lang=EN`;
        $.getJSON(statsUrl, function (statsJson) {
            const realValuesByDimension = {};
            const dimensionsJson = statsJson.dimension;
            for (const dimKey in dimensionsJson) {
                const categories = dimensionsJson[dimKey]?.category?.index || {};
                realValuesByDimension[dimKey] = Object.keys(categories);
            }

            // Τώρα πάρε το DSD XML και προχώρα
            $.get(proxiedDsd, function (dsdXml) {
                const concepts = {};
                $(dsdXml).find("*").filter(function () {
                    return this.tagName.toLowerCase().endsWith("concept");
                }).each(function () {
                    const id = $(this).attr("id");
                    const name = $(this).find("*").filter(function () {
                        return this.tagName.toLowerCase().endsWith("name");
                    }).first().text();
                    if (id && name) {
                        concepts[id] = name;
                    }
                });

                const dimensions = $(dsdXml).find("*").filter(function () {
                    return this.tagName.toLowerCase().endsWith("dimension");
                });

                console.log("Found dimensions:", dimensions.length);
                $("#filters").empty();
                dimensions.each(function () {
                    const $dim = $(this);
                    const conceptRef = $dim.attr("conceptRef") || $dim.attr("id");
                    if (!conceptRef) return;

                    const localRep = $dim.find("*").filter(function () {
                        return this.tagName.toLowerCase().endsWith("localrepresentation");
                    });
                    if (localRep.length === 0) return;

                    const enumeration = localRep.find("*").filter(function () {
                        const tag = this.tagName.toLowerCase();
                        return tag.endsWith("enumeration") || tag.endsWith("ref");
                    });
                    if (enumeration.length === 0) return;

                    const urn = enumeration.text().trim();
                    const match = urn.match(/Codelist=ESTAT:([A-Z0-9_]+)/i);
                    if (!match) return;

                    const codelistId = match[1];
                    const codelistUrl = `https://ec.europa.eu/eurostat/api/dissemination/sdmx/3.0/structure/codelist/ESTAT/${codelistId}`;
                    const proxiedCodelist = "fetch_structure.php?url=" + encodeURIComponent(codelistUrl);

                    $.get(proxiedCodelist, function (codelistXml) {
                        const xmlText = new XMLSerializer().serializeToString(codelistXml);
                        const parser = new DOMParser();
                        const xmlDoc = parser.parseFromString(xmlText, "application/xml");

                        const codelists = xmlDoc.getElementsByTagNameNS("*", "Codelist");
                        let codelistName = '';
                        for (let i = 0; i < codelists.length; i++) {
                            const cl = codelists[i];
                            if (cl.getAttribute("id") === codelistId) {
                                const children = cl.childNodes;
                                for (let j = 0; j < children.length; j++) {
                                    const child = children[j];
                                    if (child.nodeType === 1 && child.localName === "Name") {
                                        const lang = child.getAttribute("xml:lang") || child.getAttribute("lang");
                                        if (!lang || lang === "en") {
                                            codelistName = child.textContent.trim();
                                            break;
                                        }
                                    }
                                }
                                break;
                            }
                        }

                        const allowedCodes = realValuesByDimension[conceptRef] || [];

                        const select = $('<select></select>').attr('id', `filter-${conceptRef}`).addClass('dimension-filter');
                        select.append($('<option></option>').attr('value', '').text(`-- Select ${conceptRef} --`));

                        $(codelistXml).find("*").filter(function () {
                            return this.tagName.toLowerCase().endsWith("code");
                        }).each(function () {
                            const $code = $(this);
                            const codeId = $code.attr("id");
                            if (!allowedCodes.includes(codeId)) return; // <- Φιλτράρισμα

                            const label = $code.find("*").filter(function () {
                                return this.tagName.toLowerCase().endsWith("name");
                            }).first().text();
                            select.append($('<option></option>').attr('value', codeId).text(`${label} [${codeId}]`));
                        });

                        const wrapper = $('<div></div>').addClass('filter-block');
                        wrapper.append($(`<label><b>${codelistName || conceptRef}</b></label><br>`));
                        wrapper.append(select);
                        $('#filters').append(wrapper);
                    });
                });

                datasetCode = code;
                dimensionOrder = [];
                dimensions.each(function () {
                    const conceptRef = $(this).attr("conceptRef") || $(this).attr("id");
                    if (conceptRef) dimensionOrder.push(conceptRef);
                });
            });
        });
    });
}


let datasetCode = null;         // ορίζεται στο fetchStructureForDataset
let dimensionOrder = [];        // γεμίζει με τη σωστή σειρά των dimensions

function applyFilters() {
    const selectedValues = {};

    // Πάρε τιμές από όλα τα φίλτρα
    $(".dimension-filter").each(function () {
        const id = $(this).attr("id").replace("filter-", "");
        const value = $(this).val();
        selectedValues[id] = value || "";  // άδειο string για μη επιλεγμένα
    });

    if (!datasetCode || dimensionOrder.length === 0) {
        console.error("Missing datasetCode or dimensionOrder");
        return;
    }

    const keyParts = dimensionOrder
        .filter(dim => selectedValues[dim]) // μόνο επιλεγμένες
        .map(dim => `c[${dim}]=${selectedValues[dim]}`);
    const key = keyParts.join("&");

    const apiUrl = `https://ec.europa.eu/eurostat/api/dissemination/sdmx/3.0/data/dataflow/ESTAT/${datasetCode}/1.0?${key}`;

    console.log("Data API URL:", apiUrl);
    /*$.get("convert.php?data_url=" + encodeURIComponent(apiUrl), function (data) {
        $("#output").html("<pre>" + $('<div>').text(data).html() + "</pre>");
    });*/
    const convertUrl = "convert.php?data_url=" + encodeURIComponent(apiUrl);
    parseTurtleAndDisplay(convertUrl);
}


function parseTurtleAndDisplay(url) {
    $.get(url, function(data) {
        const lines = data.split('\n');
        const observations = [];
        let currentObs = null;

        lines.forEach(line => {
            line = line.trim();
            if (line.startsWith("ex:obs")) {
                if (currentObs) observations.push(currentObs);
                currentObs = {};
            }

            if (line.includes("sdmx:time")) {
                currentObs.time = line.match(/"(.+?)"/)?.[1];
            } else if (line.includes("sdmx:value")) {
                currentObs.value = line.match(/"(.+?)"/)?.[1];
            } else if (line.includes("sdmx:")) {
                const match = line.match(/sdmx:(\w+)\s+"(.+?)"/);
                if (match) {
                    const [, key, val] = match;
                    if (key !== "time" && key !== "value") {
                        currentObs[key] = val;
                    }
                }
            }
        });
        if (currentObs) observations.push(currentObs);

        // Get all dynamic keys
        const dimensionKeys = {};
        observations.forEach(obs => {
            for (const key in obs) {
                if (key !== "time" && key !== "value") {
                    dimensionKeys[key] = dimensionKeys[key] || new Set();
                    dimensionKeys[key].add(obs[key]);
                }
            }
        });

        // Keep only keys with multiple values
        const variableKeys = Object.keys(dimensionKeys).filter(
            key => dimensionKeys[key].size > 1
        );

        // Build table
        let html = "<table border='1' style='border-collapse: collapse;'><thead><tr>";
        html += "<th>Time</th><th>Value</th>";
        variableKeys.forEach(key => {
            html += `<th>${key}</th>`;
        });
        html += "</tr></thead><tbody>";

        observations.forEach(obs => {
            html += "<tr>";
            html += `<td>${obs.time || ""}</td><td>${obs.value || ""}</td>`;
            variableKeys.forEach(key => {
                html += `<td>${obs[key] || ""}</td>`;
            });
            html += "</tr>";
        });

        html += "</tbody></table>";

        $("#output").html(html);
    });
}