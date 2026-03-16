//VERSION=3

function setup() {
    return {
        input: ["B8A", "B12", "SCL", "dataMask"],
        output: [
            { id: "default", bands: 4 },
            { id: "index", bands: 1, sampleType: "FLOAT32" },
            { id: "eobrowserStats", bands: 2, sampleType: "FLOAT32" },
            { id: "dataMask", bands: 1 }
        ]
    };
}

function evaluatePixel(samples) {
    let val = index(samples.B8A, samples.B12);
    const indexVal = samples.dataMask === 1 ? val : NaN;
    let imgVals = null;

    if (isCloud(samples.SCL)) imgVals = [1, 1, 1, samples.dataMask];
    else if (val < -0.4) imgVals = [0.04, 0.12, 0.24, samples.dataMask];
    else if (val < -0.25) imgVals = [0.10, 0.21, 0.36, samples.dataMask];
    else if (val < -0.1) imgVals = [0.20, 0.17, 0.13, samples.dataMask];
    else if (val < 0.0) imgVals = [0.35, 0.25, 0.14, samples.dataMask];
    else if (val < 0.1) imgVals = [0.52, 0.37, 0.16, samples.dataMask];
    else if (val < 0.2) imgVals = [0.66, 0.50, 0.19, samples.dataMask];
    else if (val < 0.3) imgVals = [0.64, 0.64, 0.24, samples.dataMask];
    else if (val < 0.4) imgVals = [0.46, 0.66, 0.25, samples.dataMask];
    else if (val < 0.5) imgVals = [0.29, 0.60, 0.24, samples.dataMask];
    else imgVals = [0.12, 0.47, 0.21, samples.dataMask];

    return {
        default: imgVals,
        index: [indexVal],
        eobrowserStats: [val, isCloud(samples.SCL) ? 1 : 0],
        dataMask: [samples.dataMask]
    };
}

function isCloud(scl) {
    if (scl == 3) return false; // SC_CLOUD_SHADOW
    if (scl == 9) return true;  // SC_CLOUD_HIGH_PROBA
    if (scl == 8) return true;  // SC_CLOUD_MEDIUM_PROBA
    if (scl == 7) return false; // SC_CLOUD_LOW_PROBA
    if (scl == 10) return true; // SC_THIN_CIRRUS
    if (scl == 11) return false; // SC_SNOW_ICE
    if (scl == 1) return false; // SC_SATURATED_DEFECTIVE
    if (scl == 2) return false; // SC_DARK_FEATURE_SHADOW

    return false;
}

