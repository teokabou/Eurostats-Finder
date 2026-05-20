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
/* ΝΕΑ ΤΑΚΤΙΚΗ ΜΕ MODES */
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById("themesBtn").addEventListener("click", showThemes);
    document.getElementById("searchBtn").addEventListener("click", showSearch);
    document.getElementById("datasetSearch")
        .addEventListener("keyup", handleSearch);
});

let treeLoaded = false;

function showThemes(){
    document.getElementById("themesSection").style.display = "block";
    document.getElementById("searchSection").style.display = "none";
    document.getElementById("themesBtn").classList.add("active");
    document.getElementById("searchBtn").classList.remove("active");
    if(!treeLoaded){
        loadTree();
        treeLoaded = true;
    }
};

function showSearch(){
    document.getElementById("themesSection").style.display = "none";
    document.getElementById("searchSection").style.display = "block";
    document.getElementById("searchBtn").classList.add("active");
    document.getElementById("themesBtn").classList.remove("active");
    document.getElementById("datasetSearch").focus();
};

function loadTree(){
    const container = document.getElementById('tree-container');
    container.textContent = 'Loading...';
    fetch('toc_loader.php')
        .then(response => {
            if (!response.ok) {
                throw new Error("HTTP Error");
            }
            return response.json();
        })
        .then(data => {
            container.innerHTML = '';
            renderTree(data, container);
        })
        .catch(error => {
            container.textContent = 'Failed to load data';
        });
};

function handleSearch() {
    let q = this.value.trim();
    if (q.length < 2) {
        document.getElementById("searchResults").innerHTML = "";
        return;
    }
    fetch("search_datasets.php?q=" + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
            let html = "<ul>";
            data.forEach(item => {
                html += `
                    <li class="dataset-item search-item" data-code="${item.code}">
                        ${item.title} [${item.code}]
                    </li>
                `;
            });
            html += "</ul>";
            document.getElementById("searchResults").innerHTML = html;
            document.querySelectorAll(".search-item").forEach(item => {
                item.addEventListener("click", function () {
                    document.querySelectorAll(".dataset-item").forEach(el => {
                        el.classList.remove("selected");
                    });
                    this.classList.add("selected");
                });
            });
        });
}

// jQuery φορτωτής
$(function () {
    fetch('check_toc_update.php')
    .then(r => r.json())
    .then(data => {
        console.log("TOC update status:", data);
        if (data.update) {
            console.log("Update executed");
        }
    })
    .catch(err => console.error(err));
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

let codeLabels = {};
let dimLabels={};
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
        //  ΝΕΟ Πρώτα πάρε το statistics  για να βρεις τις ενεργές τιμές
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
                                            dimLabels[codelistId]=codelistName;
                                            break;
                                        }
                                    }
                                }
                                break;
                            }
                        }
                        const allowedCodes = realValuesByDimension[conceptRef] || [];
                        const select = $('<select></select>').attr('id', `filter-${conceptRef}`).addClass('dimension-filter');
                        select.append($('<option></option>').attr('value', '').text(`-- Select ${codelistName} --`));
                        $(codelistXml).find("*").filter(function () {
                            return this.tagName.toLowerCase().endsWith("code");
                        }).each(function () {
                            const $code = $(this);
                            const codeId = $code.attr("id");
                            if (!allowedCodes.includes(codeId)) return; 
                            const label = $code.find("*").filter(function () {
                                return this.tagName.toLowerCase().endsWith("name");
                            }).first().text();
                            select.append($('<option></option>').attr('value', codeId).text(`${label} [${codeId}]`));
                            if (!codeLabels[conceptRef]) {
                                codeLabels[conceptRef] = {};
                            }
                            codeLabels[conceptRef][codeId] = label;
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

function applyFilters() {
    $.ajax({
        url: "save_labels.php",
        type: "POST",
        contentType: "application/json",
        data: JSON.stringify(codeLabels),
        processData: false,
        success: function(res) {
            console.err("Saved labels:", res);
            parseTurtleAndDisplay(convertUrl);
        },
        error: function(xhr) {
            console.err("Label save error:", xhr.responseText);
            parseTurtleAndDisplay(convertUrl);
        }
    });
    const selectedValues = {};
    $(".dimension-filter").each(function () {
        const id = $(this).attr("id").replace("filter-", "");
        const value = $(this).val();
        selectedValues[id] = value || "";
    });
    if (!datasetCode || dimensionOrder.length === 0) {
        console.error("Missing datasetCode or dimensionOrder");
        return;
    }
    const keyParts = dimensionOrder
        .filter(dim => selectedValues[dim])
        .map(dim => `c[${dim}]=${selectedValues[dim]}`);
    const key = keyParts.join("&");
    const apiUrl = `https://ec.europa.eu/eurostat/api/dissemination/sdmx/3.0/data/dataflow/ESTAT/${datasetCode}/1.0?${key}`;
    const convertUrl = "convert.php?data_url=" + encodeURIComponent(apiUrl);

}

// neo parse
const labelMap = {};

function parseTurtleAndDisplay(url) {
    // reset
    Object.keys(labelMap).forEach(k => delete labelMap[k]);
    $.get(url, function(data) {
        const lines = data.split('\n');
        const labelMap = {};
        let currentCode = null;
        const labelStartPattern = /estatcode:(\w+)\/([\w\-]+)\s+a\s+skos:Concept/;
        const rdfsLabelPattern   = /rdfs:label\s+"([^"]+)"/;

        lines.forEach(line => {
            line = line.trim();
            const labelStart = line.match(labelStartPattern);
            if (labelStart) {
                currentCode = { dim: labelStart[1].toLowerCase(), code: labelStart[2] };
                return;
            }
            const labelValue = line.match(rdfsLabelPattern);
            if (labelValue && currentCode) {
                if (!labelMap[currentCode.dim]) labelMap[currentCode.dim] = {};
                labelMap[currentCode.dim][currentCode.code] = labelValue[1];
                return;
            }
            if (line === '.') currentCode = null;
        });

        const observations = [];
        let currentObs = null;
        const obsStartPattern   = /^estat:obs\//;
        const refPeriodPattern  = /sdmx-dimension:timePeriod\s+"([^"]+)"/;
        const obsValuePattern   = /sdmx-measure:obsValue\s+"([^"]+)"/;
        const dimensionPattern  = /(sdmx-dimension|sdmx-attribute|estatdim):(\w+)\s+estatcode:(\w+)\/([\w\-]+)/;
        lines.forEach(line => {
            line = line.trim();
            if (obsStartPattern.test(line)) {
                if (currentObs && Object.keys(currentObs).length) observations.push(currentObs);
                currentObs = {};
                return;
            }
            if (!currentObs) return;
            const timeMatch = line.match(refPeriodPattern);
            if (timeMatch) { currentObs.time = timeMatch[1]; return; }
            const valueMatch = line.match(obsValuePattern);
            if (valueMatch) { currentObs.value = valueMatch[1]; return; }
            const dimMatch = line.match(dimensionPattern);
            if (dimMatch) {
                const [,, property, codeType, codeValue] = dimMatch;
                const key    = codeType.toLowerCase();
                const label  = (labelMap[key] && labelMap[key][codeValue]) ? labelMap[key][codeValue] : codeValue;
                currentObs[key] = label;
            }
        });
        if (currentObs && Object.keys(currentObs).length) observations.push(currentObs);
        const dimensionKeys = {};
        observations.forEach(obs => {
            for (const k in obs) {
                if (k !== 'time' && k !== 'value') {
                    dimensionKeys[k] =
                        dimensionKeys[k] || new Set();
                    dimensionKeys[k].add(obs[k]);
                }
            }
        });
        const variableKeys =
            Object.keys(dimensionKeys).filter(
                k => dimensionKeys[k].size > 1
            );
        let html = `
            <table class="rdf-table">
                <thead>
                    <tr>
                        <th>Time Period</th>
                        <th>Value</th>
                        ${variableKeys.map(
                            k => `<th>${dimLabels[k.toUpperCase()] || k.toUpperCase()}</th>`
                        ).join('')}
                    </tr>
                </thead>
                <tbody>
        `;
        observations.forEach(obs => {
            html += `
                <tr>
                    <td>${obs.time || ''}</td>
                    <td>${formatNumber(obs.value)}</td>
                    ${variableKeys.map(
                        k => `<td>${obs[k] || ''}</td>`
                    ).join('')}
                </tr>
            `;
        });
        html += `
                </tbody>
            </table>
        `;
        html += `
            <p class="obs-count">
                Total observations: ${observations.length}
            </p>
        `;
        $('#output').html(html);
    });
}

function formatNumber(val) {
    if (!val) return '';
    const num = parseFloat(val);
    return isNaN(num)
        ? val
        : num.toLocaleString();
}