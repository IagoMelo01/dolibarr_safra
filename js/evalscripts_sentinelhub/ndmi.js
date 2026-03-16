//VERSION=3

function setup() {
    return {
        input: ["B8A", "B11", "SCL", "dataMask"],
        output: [
            { id: "default", bands: 4 },
            { id: "index", bands: 1, sampleType: "FLOAT32" },
            { id: "eobrowserStats", bands: 2, sampleType: "FLOAT32" },
            { id: "dataMask", bands: 1 }
        ]
    };
}

function evaluatePixel(samples) {
    let val = (samples.B8A - samples.B11) / (samples.B8A + samples.B11);
    let imgVals = null;
    const indexVal = samples.dataMask === 1 ? val : NaN;

    if (isCloud(samples.SCL)) imgVals = [1, 1, 1, samples.dataMask]; // White for clouds
    else if (val < -0.8) imgVals = [0.502, 0, 0, samples.dataMask]; // Dark red
    else if (val < -0.7) imgVals = [0.6, 0, 0, samples.dataMask]; // Less dark red
    else if (val < -0.6) imgVals = [0.75, 0, 0, samples.dataMask]; // Slightly lighter red
    else if (val < -0.5) imgVals = [0.9, 0, 0, samples.dataMask]; // Light red
    else if (val < -0.4) imgVals = [1, 0, 0, samples.dataMask]; // Red
    else if (val < -0.3) imgVals = [1, 0.25, 0, samples.dataMask]; // Dark red-orange
    else if (val < -0.2) imgVals = [1, 0.5, 0, samples.dataMask]; // Red-orange
    else if (val < -0.1) imgVals = [1, 0.75, 0, samples.dataMask]; // Light red-orange
    else if (val < 0) imgVals = [1, 1, 0, samples.dataMask]; // Yellow
    else if (val < 0.1) imgVals = [0.75, 1, 0.5, samples.dataMask]; // Light yellow-green
    else if (val < 0.2) imgVals = [0.5, 1, 0.5, samples.dataMask]; // Light cyan
    else if (val < 0.3) imgVals = [0.25, 1, 0.75, samples.dataMask]; // Lighter cyan
    else if (val < 0.4) imgVals = [0, 1, 1, samples.dataMask]; // Cyan
    else if (val < 0.45) imgVals = [0, 0.875, 1, samples.dataMask]; // Light blue
    else if (val < 0.5) imgVals = [0, 0.75, 1, samples.dataMask]; // Lighter blue
    else imgVals = [0, 0, 0.502, samples.dataMask]; // Dark blue

    return {
        default: imgVals,
        index: [indexVal],
        eobrowserStats: [val, isCloud(samples.SCL) ? 1 : 0],
        dataMask: [samples.dataMask]
    };
}


function isCloud (scl) {
  if (scl == 3) { // SC_CLOUD_SHADOW
    return false;
  } else if (scl == 9) { // SC_CLOUD_HIGH_PROBA
    return true; 
  } else if (scl == 8) { // SC_CLOUD_MEDIUM_PROBA
    return true;
  } else if (scl == 7) { // SC_CLOUD_LOW_PROBA
    return false;
  } else if (scl == 10) { // SC_THIN_CIRRUS
    return true;
  } else if (scl == 11) { // SC_SNOW_ICE
    return false;
  } else if (scl == 1) { // SC_SATURATED_DEFECTIVE
    return false;
  } else if (scl == 2) { // SC_DARK_FEATURE_SHADOW
     return false;
  }
  return false;
}
